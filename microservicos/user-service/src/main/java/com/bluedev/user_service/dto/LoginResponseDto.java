package com.bluedev.user_service.dto;

import com.bluedev.user_service.entity.TipoUsuario;

public record LoginResponseDto(
        Integer id,
        String usuario,
        TipoUsuario tipoUsuario
) {}