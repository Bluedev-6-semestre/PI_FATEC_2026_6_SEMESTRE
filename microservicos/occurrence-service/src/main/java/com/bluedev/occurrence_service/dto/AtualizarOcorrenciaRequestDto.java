
package com.bluedev.occurrence_service.dto;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

public record AtualizarOcorrenciaRequestDto(

        @NotNull
        Integer usuarioId,

        Integer maquinaId,

        @NotBlank
        @Size(max = 100)
        String titulo,

        @NotBlank
        @Size(max = 50)
        String tipo,

        @NotBlank
        @Size(max = 50)
        String status,

        String descricao,

        @Size(max = 255)
        String imagem

) {}
