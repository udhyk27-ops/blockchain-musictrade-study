package com.example.musictrade.service;

import com.example.musictrade.dto.RegisterSongRequest;
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
import org.web3j.crypto.Credentials;
import org.web3j.protocol.Web3j;
import org.web3j.protocol.core.DefaultBlockParameterName;
import org.web3j.protocol.core.methods.request.Transaction;
import org.web3j.protocol.core.methods.response.EthCall;
import org.web3j.tx.RawTransactionManager;
import org.web3j.tx.gas.DefaultGasProvider;

import java.math.BigInteger;
import java.util.ArrayList;
import java.util.Arrays;
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

    @Value("${besu.wallet.private-key}")
    private String privateKey;

    private Credentials getCredentials() {
        return Credentials.create(privateKey);
    }

    // ─── 곡 등록 ───────────────────────────────────────
    public String registerSong(RegisterSongRequest req) throws Exception {
        Function function = new Function(
                "registerSong",
                Arrays.asList(
                        new Utf8String(req.getTitle()),
                        new Utf8String(req.getArtist()),
                        new Utf8String(req.getGenre())
                ),
                Collections.emptyList()
        );
        return sendTransaction(function);
    }

    // ─── 판매 등록 ─────────────────────────────────────
    public String listForSale(BigInteger songId, BigInteger price) throws Exception {
        Function function = new Function(
                "listForSale",
                Arrays.asList(new Uint256(songId), new Uint256(price)),
                Collections.emptyList()
        );
        return sendTransaction(function);
    }

    // ─── 판매 취소 ─────────────────────────────────────
    public String delistSong(BigInteger songId) throws Exception {
        Function function = new Function(
                "delistSong",
                Collections.singletonList(new Uint256(songId)),
                Collections.emptyList()
        );
        return sendTransaction(function);
    }

    // ─── 곡 구매 ───────────────────────────────────────
    public String buySong(BigInteger songId, BigInteger value) throws Exception {
        Credentials credentials = getCredentials();
        RawTransactionManager txManager = new RawTransactionManager(web3j, credentials, 1337L);

        Function function = new Function(
                "buySong",
                Collections.singletonList(new Uint256(songId)),
                Collections.emptyList()
        );
        String encodedFunction = FunctionEncoder.encode(function);

        return txManager.sendTransaction(
                DefaultGasProvider.GAS_PRICE,
                DefaultGasProvider.GAS_LIMIT,
                contractAddress,
                encodedFunction,
                value
        ).getTransactionHash();
    }

    // ─── 곡 조회 ───────────────────────────────────────
    public SongDto getSong(BigInteger songId) throws Exception {
        Function function = new Function(
                "getSong",
                Collections.singletonList(new Uint256(songId)),
                Collections.singletonList(new TypeReference<DynamicStruct>() {})
        );

        String encodedFunction = FunctionEncoder.encode(function);
        Credentials credentials = getCredentials();

        EthCall response = web3j.ethCall(
                Transaction.createEthCallTransaction(
                        credentials.getAddress(),
                        contractAddress,
                        encodedFunction
                ),
                DefaultBlockParameterName.LATEST
        ).send();

        // struct는 첫 32바이트(64자)가 tuple offset → 건너뛰기
        String rawData = response.getValue();
        String strippedData = "0x" + rawData.substring(66); // 0x + 32bytes offset 제거

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
        return parseTradeRecords((DynamicArray<?>) result.get(0));
    }

    // ─── 공통: 트랜잭션 전송 ───────────────────────────
    private String sendTransaction(Function function) throws Exception {
        Credentials credentials = getCredentials();
        RawTransactionManager txManager = new RawTransactionManager(web3j, credentials, 1337L);
        String encodedFunction = FunctionEncoder.encode(function);

        return txManager.sendTransaction(
                DefaultGasProvider.GAS_PRICE,
                DefaultGasProvider.GAS_LIMIT,
                contractAddress,
                encodedFunction,
                BigInteger.ZERO
        ).getTransactionHash();
    }

    // ─── 공통: view 함수 호출 ──────────────────────────
    private List<Type> callFunction(Function function) throws Exception {
        String encodedFunction = FunctionEncoder.encode(function);
        Credentials credentials = getCredentials();

        EthCall response = web3j.ethCall(
                Transaction.createEthCallTransaction(
                        credentials.getAddress(),
                        contractAddress,
                        encodedFunction
                ),
                DefaultBlockParameterName.LATEST
        ).send();

        return FunctionReturnDecoder.decode(response.getValue(), function.getOutputParameters());
    }

    // ─── 공통: TradeRecord 파싱 ────────────────────────
    private List<TradeRecordDto> parseTradeRecords(DynamicArray<?> array) {
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