
package com.bluedev.occurrence_service.controller;

import com.bluedev.occurrence_service.dto.AtualizarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.CriarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.OcorrenciaResponseDto;
import com.bluedev.occurrence_service.service.OcorrenciaService;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/ocorrencias")
public class OcorrenciaController {

    private final OcorrenciaService ocorrenciaService;

    public OcorrenciaController(OcorrenciaService ocorrenciaService) {
        this.ocorrenciaService = ocorrenciaService;
    }

    @GetMapping
    public List<OcorrenciaResponseDto> listarTodas() {
        return ocorrenciaService.listarTodas();
    }

    @GetMapping("/{id}")
    public OcorrenciaResponseDto buscarPorId(@PathVariable Integer id) {
        return ocorrenciaService.buscarPorId(id);
    }

    @PostMapping
    public ResponseEntity<OcorrenciaResponseDto> criar(
            @Valid @RequestBody CriarOcorrenciaRequestDto request) {

        OcorrenciaResponseDto ocorrencia = ocorrenciaService.criar(request);

        return ResponseEntity.status(HttpStatus.CREATED).body(ocorrencia);
    }

    @PutMapping("/{id}")
    public OcorrenciaResponseDto atualizar(
            @PathVariable Integer id,
            @Valid @RequestBody AtualizarOcorrenciaRequestDto request) {

        return ocorrenciaService.atualizar(id, request);
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> excluir(@PathVariable Integer id) {
        ocorrenciaService.excluir(id);
        return ResponseEntity.noContent().build();
    }
}
