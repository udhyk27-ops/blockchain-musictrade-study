package com.example.musictrade;

import io.github.cdimascio.dotenv.Dotenv;
import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication
public class MusictradeApplication {

	public static void main(String[] args) {

		Dotenv dotenv = Dotenv.load();
		System.setProperty("BESU_RPC_URL", dotenv.get("BESU_RPC_URL"));
		System.setProperty("BESU_CONTRACT_ADDRESS", dotenv.get("BESU_CONTRACT_ADDRESS"));
		System.setProperty("BESU_PRIVATE_KEY", dotenv.get("BESU_PRIVATE_KEY"));


		SpringApplication.run(MusictradeApplication.class, args);

	}

}
