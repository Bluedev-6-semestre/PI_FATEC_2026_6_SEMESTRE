package com.bluedev.machine.entity;

import jakarta.persistence.*;
import java.time.LocalDate;
import java.time.LocalDateTime;

@Entity
@Table(name = "maquinas")
public class Maquina {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Integer id;

    @Column(name = "usuario_id", nullable = false)
    private Integer usuarioId;

    @Column(nullable = false, length = 100)
    private String nome;

    @Column(nullable = false, length = 50)
    private String codigo;

    @Column(nullable = false, length = 100)
    private String modelo;

    @Column(nullable = false, length = 100)
    private String fabricante;

    @Column(name = "numero_serie", nullable = false, length = 100)
    private String numeroSerie;

    @Column(name = "data_aquisicao", nullable = false)
    private LocalDate dataAquisicao;

    @Column(nullable = false, length = 150)
    private String localizacao;

    @Column(name = "status_maquina", nullable = false, length = 50)
    private String statusMaquina;

    @Column(columnDefinition = "TEXT")
    private String descricao;

    @Column(name = "data_criacao", insertable = false, updatable = false)
    private LocalDateTime dataCriacao;

    @Column(name = "data_atualizacao", insertable = false, updatable = false)
    private LocalDateTime dataAtualizacao;

    public Maquina() {
    }

    public Integer getId() {
        return id;
    }

    public Integer getUsuarioId() {
        return usuarioId;
    }

    public String getNome() {
        return nome;
    }

    public String getCodigo() {
        return codigo;
    }

    public String getModelo() {
        return modelo;
    }

    public String getFabricante() {
        return fabricante;
    }

    public String getNumeroSerie() {
        return numeroSerie;
    }

    public LocalDate getDataAquisicao() {
        return dataAquisicao;
    }

    public String getLocalizacao() {
        return localizacao;
    }

    public String getStatusMaquina() {
        return statusMaquina;
    }

    public String getDescricao() {
        return descricao;
    }

    public LocalDateTime getDataCriacao() {
        return dataCriacao;
    }

    public LocalDateTime getDataAtualizacao() {
        return dataAtualizacao;
    }
}
