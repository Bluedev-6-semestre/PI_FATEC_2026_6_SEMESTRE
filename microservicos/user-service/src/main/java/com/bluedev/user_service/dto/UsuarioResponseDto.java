package com.bluedev.user_service.dto;

import com.bluedev.user_service.entity.TipoUsuario;

import java.util.List;

public record UsuarioResponseDto(
        Integer id,
        String usuario,
        TipoUsuario tipoUsuario,
        String nome,
        Boolean ativo,
        List<String> permissoes,
        Integer funcionarioId
) {}
