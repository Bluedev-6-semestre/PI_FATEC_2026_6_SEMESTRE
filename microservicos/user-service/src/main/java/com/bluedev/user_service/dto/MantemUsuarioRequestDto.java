package com.bluedev.user_service.dto;

import com.bluedev.user_service.entity.TipoUsuario;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;

import java.util.List;

public record MantemUsuarioRequestDto(

        @NotBlank
        @Size(min = 3, max = 50)
        @Pattern(regexp = "^[A-Za-z0-9._-]+$",
                message = "Use apenas letras, numeros, ponto, hifen ou sublinhado.")
        String usuario,

        @Size(max = 72)
        String senha,

        @NotNull
        TipoUsuario tipoUsuario,

        @Size(max = 100)
        String nome,

        // Opcionais: se omitidos, o usuario e criado ativo e (se funcionario) com todas as permissoes.
        Boolean ativo,

        List<String> permissoes,

        Integer funcionarioId

) {}
