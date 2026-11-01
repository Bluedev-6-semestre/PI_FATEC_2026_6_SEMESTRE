package com.bluedev.user_service.dto;

import com.bluedev.user_service.entity.TipoUsuario;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;

public record MantemUsuarioRequestDto(

        @NotBlank
        String usuario,

        String senha,

        @NotNull
        TipoUsuario tipoUsuario

) {}