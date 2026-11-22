package com.bluedev.user_service.dto;

import com.bluedev.user_service.entity.TipoUsuario;

import java.util.List;

public record LoginResponseDto(
        Integer id,
        String usuario,
        TipoUsuario tipoUsuario,
        String nome,
        List<String> permissoes
) {}
