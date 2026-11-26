
package com.bluedev.occurrence_service.service;

import com.bluedev.occurrence_service.client.MaquinaClient;
import com.bluedev.occurrence_service.dto.AtualizarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.CriarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.OcorrenciaResponseDto;
import com.bluedev.occurrence_service.entity.Ocorrencia;
import com.bluedev.occurrence_service.repository.OcorrenciaRepository;
import feign.FeignException;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;
import java.util.Optional;

@Service
public class OcorrenciaService {

    /** Caminho da foto no gateway; o "v" muda a cada envio para os clientes recarregarem. */
    private static final String CAMINHO_IMAGEM = "/api/ocorrencias/%d/imagem?v=%d";

    private final OcorrenciaRepository ocorrenciaRepository;
    private final MaquinaClient maquinaClient;
    private final ImagemStorageService imagemStorage;

    public OcorrenciaService(
            OcorrenciaRepository ocorrenciaRepository,
            MaquinaClient maquinaClient,
            ImagemStorageService imagemStorage) {
        this.ocorrenciaRepository = ocorrenciaRepository;
        this.maquinaClient = maquinaClient;
        this.imagemStorage = imagemStorage;
    }

    public List<OcorrenciaResponseDto> listarTodas() {
        return ocorrenciaRepository.findAll()
                .stream()
                .map(this::converterParaResponse)
                .toList();
    }

    public OcorrenciaResponseDto buscarPorId(Integer id) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        return converterParaResponse(ocorrencia);
    }

    @Transactional
    public OcorrenciaResponseDto criar(CriarOcorrenciaRequestDto request) {
        validarMaquina(request.maquinaId());

        Ocorrencia ocorrencia = new Ocorrencia();
        ocorrencia.setUsuarioId(request.usuarioId());
        ocorrencia.setMaquinaId(request.maquinaId());
        ocorrencia.setTitulo(request.titulo());
        ocorrencia.setTipo(request.tipo());
        ocorrencia.setStatus(request.status());
        ocorrencia.setDescricao(request.descricao());
        ocorrencia.setImagem(request.imagem());

        Ocorrencia salva = ocorrenciaRepository.save(ocorrencia);

        return converterParaResponse(salva);
    }

    @Transactional
    public OcorrenciaResponseDto atualizar(
            Integer id,
            AtualizarOcorrenciaRequestDto request) {

        validarMaquina(request.maquinaId());

        Ocorrencia ocorrencia = buscarEntidadePorId(id);

        ocorrencia.setUsuarioId(request.usuarioId());
        ocorrencia.setMaquinaId(request.maquinaId());
        ocorrencia.setTitulo(request.titulo());
        ocorrencia.setTipo(request.tipo());
        ocorrencia.setStatus(request.status());
        ocorrencia.setDescricao(request.descricao());
        ocorrencia.setImagem(request.imagem());

        Ocorrencia atualizada = ocorrenciaRepository.save(ocorrencia);

        return converterParaResponse(atualizada);
    }

    @Transactional
    public void excluir(Integer id) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        ocorrenciaRepository.delete(ocorrencia);
        imagemStorage.remover(id);
    }

    @Transactional
    public OcorrenciaResponseDto salvarImagem(Integer id, MultipartFile arquivo) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        imagemStorage.salvar(id, arquivo);
        ocorrencia.setImagem(String.format(CAMINHO_IMAGEM, id, System.currentTimeMillis()));
        return converterParaResponse(ocorrenciaRepository.save(ocorrencia));
    }

    public Optional<ImagemStorageService.Imagem> carregarImagem(Integer id) {
        buscarEntidadePorId(id);
        return imagemStorage.carregar(id);
    }

    @Transactional
    public void removerImagem(Integer id) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        imagemStorage.remover(id);
        // Só limpa o campo se a foto era deste serviço (o site antigo guardava outro caminho).
        if (ocorrencia.getImagem() != null
                && ocorrencia.getImagem().startsWith("/api/ocorrencias/")) {
            ocorrencia.setImagem(null);
            ocorrenciaRepository.save(ocorrencia);
        }
    }

    private void validarMaquina(Integer maquinaId) {
        if (maquinaId == null) {
            return;
        }

        try {
            maquinaClient.buscarPorId(maquinaId);
        } catch (FeignException.NotFound exception) {
            throw new ResponseStatusException(
                    HttpStatus.NOT_FOUND,
                    "Máquina não encontrada."
            );
        } catch (FeignException exception) {
            throw new ResponseStatusException(
                    HttpStatus.SERVICE_UNAVAILABLE,
                    "Não foi possível consultar o serviço de máquinas."
            );
        }
    }

    private Ocorrencia buscarEntidadePorId(Integer id) {
        return ocorrenciaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(
                        HttpStatus.NOT_FOUND,
                        "Ocorrência não encontrada."
                ));
    }

    private OcorrenciaResponseDto converterParaResponse(Ocorrencia ocorrencia) {
        return new OcorrenciaResponseDto(
                ocorrencia.getId(),
                ocorrencia.getUsuarioId(),
                ocorrencia.getMaquinaId(),
                ocorrencia.getTitulo(),
                ocorrencia.getTipo(),
                ocorrencia.getStatus(),
                ocorrencia.getDescricao(),
                ocorrencia.getImagem(),
                ocorrencia.getDataCriacao()
        );
    }
}
