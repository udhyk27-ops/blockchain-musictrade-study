package com.example.musictrade.dto;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.math.BigInteger;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class SongDto { ///  곡 정보 응답용
    private BigInteger id;
    private String title;
    private String artist;
    private String genre;
    private String owner;
    private BigInteger price;
    private boolean forSale;
    private BigInteger registeredAt;
}