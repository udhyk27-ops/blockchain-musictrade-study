package com.example.musictrade.controller;

import com.example.musictrade.dto.RegisterSongRequest;
import com.example.musictrade.dto.SongDto;
import com.example.musictrade.dto.TradeRecordDto;
import com.example.musictrade.service.MusicTradeService;
import lombok.RequiredArgsConstructor;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.math.BigInteger;
import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/music")
@RequiredArgsConstructor
public class MusicTradeController {

    private final MusicTradeService musicTradeService;

    // 곡 등록
    @PostMapping("/register")
    public ResponseEntity<Map<String, String>> registerSong(@RequestBody RegisterSongRequest request) throws Exception {
        String txHash = musicTradeService.registerSong(request);
        return ResponseEntity.ok(Map.of("txHash", txHash));
    }

    // 판매 등록
    @PostMapping("/list/{songId}")
    public ResponseEntity<Map<String, String>> listForSale(
            @PathVariable BigInteger songId,
            @RequestParam BigInteger price) throws Exception {
        String txHash = musicTradeService.listForSale(songId, price);
        return ResponseEntity.ok(Map.of("txHash", txHash));
    }

    // 판매 취소
    @PostMapping("/delist/{songId}")
    public ResponseEntity<Map<String, String>> delistSong(@PathVariable BigInteger songId) throws Exception {
        String txHash = musicTradeService.delistSong(songId);
        return ResponseEntity.ok(Map.of("txHash", txHash));
    }

    // 곡 구매
    @PostMapping("/buy/{songId}")
    public ResponseEntity<Map<String, String>> buySong(
            @PathVariable BigInteger songId,
            @RequestParam BigInteger value) throws Exception {
        String txHash = musicTradeService.buySong(songId, value);
        return ResponseEntity.ok(Map.of("txHash", txHash));
    }

    // 곡 조회
    @GetMapping("/song/{songId}")
    public ResponseEntity<SongDto> getSong(@PathVariable BigInteger songId) throws Exception {
        return ResponseEntity.ok(musicTradeService.getSong(songId));
    }

    // 전체 곡 수
    @GetMapping("/total")
    public ResponseEntity<BigInteger> totalSongs() throws Exception {
        return ResponseEntity.ok(musicTradeService.totalSongs());
    }

    // 판매 중인 곡 목록
    @GetMapping("/forsale")
    public ResponseEntity<List<BigInteger>> getForSaleList() throws Exception {
        return ResponseEntity.ok(musicTradeService.getForSaleList());
    }

    // 소유자별 곡 목록
    @GetMapping("/owner/{address}")
    public ResponseEntity<List<BigInteger>> getSongsByOwner(@PathVariable String address) throws Exception {
        return ResponseEntity.ok(musicTradeService.getSongsByOwner(address));
    }

    // 전체 거래 이력
    @GetMapping("/trades")
    public ResponseEntity<List<TradeRecordDto>> getTradeHistory() throws Exception {
        return ResponseEntity.ok(musicTradeService.getTradeHistory());
    }
}