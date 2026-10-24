package com.bluedev.machine.controller;

import com.bluedev.machine.dto.MaquinaResponse;
import com.bluedev.machine.service.MaquinaService;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;

@RestController
@RequestMapping("/api/maquinas")
public class MaquinaController {

    private final MaquinaService maquinaService;

    public MaquinaController(MaquinaService maquinaService) {
        this.maquinaService = maquinaService;
    }

    @GetMapping
    public List<MaquinaResponse> listarTodas() {
        return maquinaService.listarTodas();
    }
}