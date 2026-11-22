package com.bluedev.user_service.entity;

import java.util.Arrays;
import java.util.List;

/**
 * Permissoes que podem ser concedidas a um funcionario.
 * Os nomes sao os mesmos usados pelo Backoffice web e pelo Mobile.
 */
public final class Permissoes {

    public static final String VER_CADASTROS = "ver_cadastros";
    public static final String REGISTRAR_OCORRENCIAS = "registrar_ocorrencias";
    public static final String GERENCIAR_OCORRENCIAS = "gerenciar_ocorrencias";
    public static final String VER_RELATORIOS = "ver_relatorios";

    public static final List<String> TODAS = List.of(
            VER_CADASTROS,
            REGISTRAR_OCORRENCIAS,
            GERENCIAR_OCORRENCIAS,
            VER_RELATORIOS
    );

    private Permissoes() {
    }

    // Texto salvo no banco -> lista. Vazio/nulo = permissoes padrao (todas).
    public static List<String> deTexto(String texto) {
        if (texto == null) {
            return TODAS;
        }
        if (texto.isBlank()) {
            return List.of();
        }
        return Arrays.stream(texto.split(","))
                .map(String::trim)
                .filter(TODAS::contains)
                .distinct()
                .toList();
    }

    public static String paraTexto(List<String> permissoes) {
        return String.join(",", permissoes);
    }

    public static boolean saoValidas(List<String> permissoes) {
        return TODAS.containsAll(permissoes);
    }
}
