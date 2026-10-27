
package com.bluedev.machine.dto;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.LocalDate;

public record MantemMaquinaRequestDto(
        @NotNull
        Integer usuarioId,

        @NotBlank
        @Size(max = 100)
        String nome,

        @NotBlank
        @Size(max = 50)
        String codigo,

        @NotBlank
        @Size(max = 100)
        String modelo,

        @NotBlank
        @Size(max = 100)
        String fabricante,

        @NotBlank
        @Size(max = 100)
        String numeroSerie,

        @NotNull
        LocalDate dataAquisicao,

        @NotBlank
        @Size(max = 150)
        String localizacao,

        @NotBlank
        @Size(max = 50)
        String statusMaquina,

        String descricao
) {
}
