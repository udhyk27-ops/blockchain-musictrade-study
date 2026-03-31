package com.example.musictrade.dto;

import lombok.Data;

@Data
public class RegisterSongRequest { /// 곡 등록 요청용
    private String title;
    private String artist;
    private String genre;
}