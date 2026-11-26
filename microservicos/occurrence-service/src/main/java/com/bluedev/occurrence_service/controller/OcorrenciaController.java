
package com.bluedev.occurrence_service.controller;

import com.bluedev.occurrence_service.dto.AtualizarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.CriarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.OcorrenciaResponseDto;
import com.bluedev.occurrence_service.service.OcorrenciaService;
import jakarta.validation.Valid;
import org.springframework.core.io.FileSystemResource;
import org.springframework.core.io.Resource;
import org.springframework.http.CacheControl;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ProblemDetail;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MaxUploadSizeExceededException;
import org.springframework.web.multipart.MultipartFile;

import java.time.Duration;
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

    /** Envia ou troca a foto da ocorrência (multipart, campo "arquivo"). */
    @PostMapping(value = "/{id}/imagem", consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
    public OcorrenciaResponseDto enviarImagem(
            @PathVariable Integer id,
            @RequestParam("arquivo") MultipartFile arquivo) {

        return ocorrenciaService.salvarImagem(id, arquivo);
    }

    @GetMapping("/{id}/imagem")
    public ResponseEntity<Resource> buscarImagem(@PathVariable Integer id) {
        return ocorrenciaService.carregarImagem(id)
                .map(imagem -> ResponseEntity.ok()
                        .contentType(imagem.tipo())
                        .cacheControl(CacheControl.maxAge(Duration.ofDays(1)))
                        .body((Resource) new FileSystemResource(imagem.arquivo())))
                .orElseGet(() -> ResponseEntity.notFound().build());
    }

    @DeleteMapping("/{id}/imagem")
    public ResponseEntity<Void> removerImagem(@PathVariable Integer id) {
        ocorrenciaService.removerImagem(id);
        return ResponseEntity.noContent().build();
    }

    @ExceptionHandler(MaxUploadSizeExceededException.class)
    public ProblemDetail imagemGrandeDemais() {
        return ProblemDetail.forStatusAndDetail(
                HttpStatus.PAYLOAD_TOO_LARGE, "A imagem deve ter no máximo 5 MB.");
    }
}
