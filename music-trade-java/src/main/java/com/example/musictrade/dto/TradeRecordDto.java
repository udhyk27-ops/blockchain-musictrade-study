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
public class TradeRecordDto { /// 거래이력 응답용
    private BigInteger songId;
    private String seller;
    private String buyer;
    private BigInteger price;
    private BigInteger tradedAt;
}