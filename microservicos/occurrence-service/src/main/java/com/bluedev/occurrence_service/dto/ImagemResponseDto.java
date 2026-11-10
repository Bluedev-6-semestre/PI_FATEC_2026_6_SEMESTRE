package com.bluedev.occurrence_service.dto;

public record ImagemResponseDto(
        String chave,
        String url,
        long expiracaoEmSegundos
) {}
