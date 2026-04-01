package com.example.musictrade.service;

import com.example.musictrade.dto.SongDto;
import com.example.musictrade.dto.TradeRecordDto;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Service;
import org.web3j.abi.FunctionEncoder;
import org.web3j.abi.FunctionReturnDecoder;
import org.web3j.abi.TypeReference;
import org.web3j.abi.datatypes.*;
import org.web3j.abi.datatypes.generated.Uint256;
import org.web3j.protocol.Web3j;
import org.web3j.protocol.core.DefaultBlockParameterName;
import org.web3j.protocol.core.methods.request.Transaction;
import org.web3j.protocol.core.methods.response.EthCall;

import java.math.BigInteger;
import java.util.ArrayList;
import java.util.Collections;
import java.util.List;
import java.util.stream.Collectors;

@Slf4j
@Service
@RequiredArgsConstructor
public class MusicTradeService {

    private final Web3j web3j;

    @Value("${besu.contract.address}")
    private String contractAddress;

    private static final String ZERO_ADDRESS = "0x0000000000000000000000000000000000000000";

    // ─── 곡 조회 ───────────────────────────────────────
    public SongDto getSong(BigInteger songId) throws Exception {
        Function function = new Function(
                "getSong",
                Collections.singletonList(new Uint256(songId)),
                Collections.singletonList(new TypeReference<DynamicStruct>() {})
        );

        String encodedFunction = FunctionEncoder.encode(function);

        EthCall response = web3j.ethCall(
                Transaction.createEthCallTransaction(
                        ZERO_ADDRESS,
                        contractAddress,
                        encodedFunction
                ),
                DefaultBlockParameterName.LATEST
        ).send();

        String rawData = response.getValue();

        // rawData가 null이거나 너무 짧으면 빈 결과 반환
        if (rawData == null || rawData.length() < 66) {
            log.warn("getSong rawData null or too short: {}", rawData);
            return null;
        }

        String strippedData = "0x" + rawData.substring(66);

        List<TypeReference<Type>> outputParams = new ArrayList<>();
        outputParams.add((TypeReference) new TypeReference<Uint256>() {});
        outputParams.add((TypeReference) new TypeReference<Utf8String>() {});
        outputParams.add((TypeReference) new TypeReference<Utf8String>() {});
        outputParams.add((TypeReference) new TypeReference<Utf8String>() {});
        outputParams.add((TypeReference) new TypeReference<Address>() {});
        outputParams.add((TypeReference) new TypeReference<Uint256>() {});
        outputParams.add((TypeReference) new TypeReference<Bool>() {});
        outputParams.add((TypeReference) new TypeReference<Uint256>() {});

        List<Type> decoded = FunctionReturnDecoder.decode(strippedData, outputParams);

        if (decoded == null || decoded.isEmpty()) {
            log.warn("getSong decoded is empty for songId: {}", songId);
            return null;
        }

        return SongDto.builder()
                .id(((Uint256) decoded.get(0)).getValue())
                .title(((Utf8String) decoded.get(1)).getValue())
                .artist(((Utf8String) decoded.get(2)).getValue())
                .genre(((Utf8String) decoded.get(3)).getValue())
                .owner(((Address) decoded.get(4)).getValue())
                .price(((Uint256) decoded.get(5)).getValue())
                .forSale(((Bool) decoded.get(6)).getValue())
                .registeredAt(((Uint256) decoded.get(7)).getValue())
                .build();
    }

    // ─── 전체 곡 수 ────────────────────────────────────
    public BigInteger totalSongs() throws Exception {
        Function function = new Function(
                "totalSongs",
                Collections.emptyList(),
                Collections.singletonList(new TypeReference<Uint256>() {})
        );
        List<Type> result = callFunction(function);

        if (result == null || result.isEmpty()) {
            return BigInteger.ZERO;
        }
        return ((Uint256) result.get(0)).getValue();
    }

    // ─── 판매 중인 곡 목록 ─────────────────────────────
    public List<BigInteger> getForSaleList() throws Exception {
        Function function = new Function(
                "getForSaleList",
                Collections.emptyList(),
                Collections.singletonList(new TypeReference<DynamicArray<Uint256>>() {})
        );
        List<Type> result = callFunction(function);

        if (result == null || result.isEmpty()) {
            return Collections.emptyList();
        }
        return ((DynamicArray<Uint256>) result.get(0))
                .getValue()
                .stream()
                .map(Uint256::getValue)
                .collect(Collectors.toList());
    }

    // ─── 소유자별 곡 목록 ──────────────────────────────
    public List<BigInteger> getSongsByOwner(String ownerAddress) throws Exception {
        Function function = new Function(
                "getSongsByOwner",
                Collections.singletonList(new Address(ownerAddress)),
                Collections.singletonList(new TypeReference<DynamicArray<Uint256>>() {})
        );
        List<Type> result = callFunction(function);

        if (result == null || result.isEmpty()) {
            return Collections.emptyList();
        }
        return ((DynamicArray<Uint256>) result.get(0))
                .getValue()
                .stream()
                .map(Uint256::getValue)
                .collect(Collectors.toList());
    }

    // ─── 전체 거래 이력 ────────────────────────────────
    public List<TradeRecordDto> getTradeHistory() throws Exception {
        Function function = new Function(
                "getTradeHistory",
                Collections.emptyList(),
                Collections.singletonList(new TypeReference<DynamicArray<DynamicStruct>>() {})
        );
        List<Type> result = callFunction(function);

        if (result == null || result.isEmpty()) {
            return Collections.emptyList();
        }
        return parseTradeRecords((DynamicArray<?>) result.get(0));
    }

    // ─── 공통: view 함수 호출 ──────────────────────────
    private List<Type> callFunction(Function function) throws Exception {
        String encodedFunction = FunctionEncoder.encode(function);

        EthCall response = web3j.ethCall(
                Transaction.createEthCallTransaction(
                        ZERO_ADDRESS,
                        contractAddress,
                        encodedFunction
                ),
                DefaultBlockParameterName.LATEST
        ).send();

        String value = response.getValue();
        if (value == null || value.equals("0x")) {
            log.warn("callFunction returned null or empty for: {}", function.getName());
            return Collections.emptyList();
        }

        return FunctionReturnDecoder.decode(value, function.getOutputParameters());
    }

    // ─── 공통: TradeRecord 파싱 ────────────────────────
    private List<TradeRecordDto> parseTradeRecords(DynamicArray<?> array) {
        if (array == null || array.getValue().isEmpty()) {
            return Collections.emptyList();
        }
        return array.getValue().stream().map(item -> {
            List<Type> fields = ((DynamicStruct) item).getValue();
            return TradeRecordDto.builder()
                    .songId(((Uint256) fields.get(0)).getValue())
                    .seller(((Address) fields.get(1)).getValue())
                    .buyer(((Address) fields.get(2)).getValue())
                    .price(((Uint256) fields.get(3)).getValue())
                    .tradedAt(((Uint256) fields.get(4)).getValue())
                    .build();
        }).collect(Collectors.toList());
    }
}