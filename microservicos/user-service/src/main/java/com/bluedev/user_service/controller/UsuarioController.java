package com.bluedev.user_service.controller;

import com.bluedev.user_service.dto.*;
import com.bluedev.user_service.entity.TipoUsuario;
import com.bluedev.user_service.service.UsuarioService;

import jakarta.validation.Valid;
import lombok.RequiredArgsConstructor;

import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;

import java.util.List;

@RestController
@RequestMapping("/api/usuarios")
@RequiredArgsConstructor
public class UsuarioController {

    private final UsuarioService usuarioService;

    @PostMapping
    public ResponseEntity<UsuarioResponseDto> cadastrar(
            @Valid @RequestBody MantemUsuarioRequestDto request
    ) {
        return ResponseEntity
                .status(HttpStatus.CREATED)
                .body(usuarioService.cadastrar(request));
    }

    // Filtros opcionais: q (usuario/nome), tipo (ADMIN|FUNCIONARIO) e ativo (true|false).
    @GetMapping
    public ResponseEntity<List<ListarUsuarioResponseDto>> listarTodos(
            @RequestParam(required = false) String q,
            @RequestParam(required = false) TipoUsuario tipo,
            @RequestParam(required = false) Boolean ativo
    ) {

        return ResponseEntity.ok(usuarioService.listar(q, tipo, ativo));
    }

    @GetMapping("/{id}")
    public ResponseEntity<UsuarioResponseDto> buscarPorId(
            @PathVariable Integer id
    ) {
        return ResponseEntity.ok(usuarioService.buscarPorId(id));
    }

    @PutMapping("/{id}")
    public ResponseEntity<UsuarioResponseDto> atualizar(
            @PathVariable Integer id,
            @Valid @RequestBody MantemUsuarioRequestDto request
    ) {
        return ResponseEntity.ok(
                usuarioService.atualizar(id, request)
        );
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> excluir(
            @PathVariable Integer id
    ) {
        usuarioService.excluir(id);

        return ResponseEntity.noContent().build();
    }

    @PostMapping("/login")
    public ResponseEntity<LoginResponseDto> login(
            @Valid @RequestBody LoginRequestDto request
    ) {
        return ResponseEntity.ok(
                usuarioService.login(request)
        );
    }
}