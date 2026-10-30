package com.bluedev.occurrence_service.entity;

import jakarta.persistence.*;
import lombok.Getter;
import lombok.NoArgsConstructor;
import lombok.Setter;

import java.time.LocalDateTime;

@Getter
@Setter
@NoArgsConstructor
@Entity
@Table(name = "ocorrencias")
public class Ocorrencia {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Integer id;

    @Column(name = "usuario_id", nullable = false)
    private Integer usuarioId;

    @Column(name = "maquina_id")
    private Integer maquinaId;

    @Column(nullable = false, length = 100)
    private String titulo;

    @Column(nullable = false, length = 50)
    private String tipo;

    @Column(nullable = false, length = 50)
    private String status;

    @Column(columnDefinition = "TEXT")
    private String descricao;

    @Column(length = 255)
    private String imagem;

    @Column(name = "data_criacao", insertable = false, updatable = false)
    private LocalDateTime dataCriacao;
}
