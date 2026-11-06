
package com.bluedev.machine.controller;

import com.bluedev.machine.dto.ListarMaquinaResponseDto;
import com.bluedev.machine.dto.MantemMaquinaRequestDto;
import com.bluedev.machine.dto.MaquinaResponseDto;
import com.bluedev.machine.service.MaquinaService;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/maquinas")
public class MaquinaController {

    private final MaquinaService maquinaService;

    public MaquinaController(MaquinaService maquinaService) {
        this.maquinaService = maquinaService;
    }

    @GetMapping
    public List<ListarMaquinaResponseDto> listarTodas() {
        return maquinaService.listarTodas();
    }

    @GetMapping("/{id}")
    public MaquinaResponseDto buscarPorId(@PathVariable Integer id) {
        return maquinaService.buscarPorId(id);
    }

    @PostMapping
    public ResponseEntity<MaquinaResponseDto> criar(
            @Valid @RequestBody MantemMaquinaRequestDto request) {

        MaquinaResponseDto maquina = maquinaService.criar(request);

        return ResponseEntity
                .status(HttpStatus.CREATED)
                .body(maquina);
    }

    @PutMapping("/{id}")
    public MaquinaResponseDto atualizar(
            @PathVariable Integer id,
            @Valid @RequestBody MantemMaquinaRequestDto request) {

        return maquinaService.atualizar(id, request);
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> excluir(@PathVariable Integer id) {
        maquinaService.excluir(id);
        return ResponseEntity.noContent().build();
    }
}
