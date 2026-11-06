
package com.bluedev.occurrence_service.dto;

import java.time.LocalDateTime;

public record OcorrenciaResponseDto(
        Integer id,
        Integer usuarioId,
        Integer maquinaId,
        String titulo,
        String tipo,
        String status,
        String descricao,
        String imagem,
        LocalDateTime dataCriacao
) {}
