package com.example.musictrade.controller;

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

    // 곡 조회
    @GetMapping("/song/{songId}")
    public ResponseEntity<SongDto> getSong(@PathVariable BigInteger songId) throws Exception {
        return ResponseEntity.ok(musicTradeService.getSong(songId));
    }

    // 전체 곡 수
    @GetMapping("/total")
    public ResponseEntity<Map<String, String>> totalSongs() throws Exception {
        return ResponseEntity.ok(Map.of("totalSongs", musicTradeService.totalSongs().toString()));
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