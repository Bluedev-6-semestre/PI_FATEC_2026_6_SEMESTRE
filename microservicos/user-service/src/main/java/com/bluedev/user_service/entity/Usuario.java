package com.bluedev.user_service.entity;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

@Getter
@Setter
@NoArgsConstructor
@Entity
@Table(name = "usuarios")
public class Usuario {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Integer id;

    @Column(nullable = false, unique = true, length = 50)
    private String usuario;

    @Column(nullable = false, length = 255)
    private String senha;

    @Enumerated(EnumType.STRING)
    @Column(name = "tipo_usuario", nullable = false, length = 20)
    private TipoUsuario tipoUsuario;

    @Column(length = 100)
    private String nome;

    @Column(nullable = false)
    private Boolean ativo = true;

    // Permissoes separadas por virgula (ex.: "ver_cadastros,registrar_ocorrencias").
    // Administradores tem acesso total e nao usam este campo.
    @Column(length = 255)
    private String permissoes;

    @Column(name = "funcionario_id")
    private Integer funcionarioId;
}
