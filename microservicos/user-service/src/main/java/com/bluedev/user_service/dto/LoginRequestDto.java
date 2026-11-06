package com.bluedev.user_service.dto;

import jakarta.validation.constraints.NotBlank;

public record LoginRequestDto(

        @NotBlank
        String usuario,

        @NotBlank
        String senha

) {}