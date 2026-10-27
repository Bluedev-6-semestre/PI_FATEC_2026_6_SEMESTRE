package com.bluedev.machine.dto;

import java.time.LocalDate;

public record MaquinaResponseDto(
        Integer id,
        Integer usuarioId,
        String nome,
        String codigo,
        String modelo,
        String fabricante,
        String numeroSerie,
        LocalDate dataAquisicao,
        String localizacao,
        String statusMaquina,
        String descricao
) {
}
