package com.example.musictrade.controller;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;

@Controller
public class PageController {

    @Value("${besu.contract.address}")
    private String contractAddress;

    @GetMapping("/")
    public String index(Model model) {
        model.addAttribute("contractAddress", contractAddress);
        return "index";
    }
}