package com.bluedev.user_service.dto;

import com.bluedev.user_service.entity.TipoUsuario;

public record ListarUsuarioResponseDto(
        Integer id,
        String usuario,
        TipoUsuario tipoUsuario
) {}