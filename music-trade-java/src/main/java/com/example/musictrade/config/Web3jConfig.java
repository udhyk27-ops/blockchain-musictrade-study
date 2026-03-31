package com.example.musictrade.config;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.web3j.protocol.Web3j;
import org.web3j.protocol.http.HttpService;

@Configuration
public class Web3jConfig {

    @Value("${besu.rpc.url}")
    private String rpcUrl;

    @Bean
    public Web3j web3j() { // Web3j 객체를 Spring Bean으로 등록
        return Web3j.build(new HttpService(rpcUrl));
    }
}