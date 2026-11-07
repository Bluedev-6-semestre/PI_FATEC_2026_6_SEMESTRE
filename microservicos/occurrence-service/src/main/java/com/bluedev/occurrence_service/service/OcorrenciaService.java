package com.bluedev.occurrence_service.service;

import com.bluedev.occurrence_service.client.MaquinaClient;
import com.bluedev.occurrence_service.dto.AtualizarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.CriarOcorrenciaRequestDto;
import com.bluedev.occurrence_service.dto.ImagemResponseDto;
import com.bluedev.occurrence_service.dto.OcorrenciaResponseDto;
import com.bluedev.occurrence_service.entity.Ocorrencia;
import com.bluedev.occurrence_service.repository.OcorrenciaRepository;
import feign.FeignException;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.transaction.support.TransactionSynchronization;
import org.springframework.transaction.support.TransactionSynchronizationManager;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@Service
public class OcorrenciaService {
    private static final Logger log = LoggerFactory.getLogger(OcorrenciaService.class);
    private final OcorrenciaRepository ocorrenciaRepository;
    private final MaquinaClient maquinaClient;
    private final S3StorageService s3StorageService;

    public OcorrenciaService(OcorrenciaRepository ocorrenciaRepository,
                             MaquinaClient maquinaClient,
                             S3StorageService s3StorageService) {
        this.ocorrenciaRepository = ocorrenciaRepository;
        this.maquinaClient = maquinaClient;
        this.s3StorageService = s3StorageService;
    }

    public List<OcorrenciaResponseDto> listarTodas() {
        return ocorrenciaRepository.findAll().stream().map(this::converterParaResponse).toList();
    }

    public OcorrenciaResponseDto buscarPorId(Integer id) {
        return converterParaResponse(buscarEntidadePorId(id));
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
        return converterParaResponse(ocorrenciaRepository.save(ocorrencia));
    }

    @Transactional
    public OcorrenciaResponseDto atualizar(Integer id, AtualizarOcorrenciaRequestDto request) {
        validarMaquina(request.maquinaId());
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        ocorrencia.setUsuarioId(request.usuarioId());
        ocorrencia.setMaquinaId(request.maquinaId());
        ocorrencia.setTitulo(request.titulo());
        ocorrencia.setTipo(request.tipo());
        ocorrencia.setStatus(request.status());
        ocorrencia.setDescricao(request.descricao());
        return converterParaResponse(ocorrenciaRepository.save(ocorrencia));
    }

    @Transactional
    public ImagemResponseDto enviarImagem(Integer id, MultipartFile arquivo) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        String chaveAntiga = ocorrencia.getImagem();
        String novaChave = s3StorageService.uploadOccurrenceImage(id, arquivo);

        try {
            ocorrencia.setImagem(novaChave);
            ocorrenciaRepository.save(ocorrencia);
            registrarLimpezaAposTransacao(novaChave, chaveAntiga);
            return criarImagemResponse(novaChave);
        } catch (RuntimeException exception) {
            // A exclusão é idempotente; o callback também pode executá-la em caso de rollback.
            tentarExcluir(novaChave);
            throw exception;
        }
    }

    @Transactional(readOnly = true)
    public ImagemResponseDto obterImagem(Integer id) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        String chave = ocorrencia.getImagem();
        if (!s3StorageService.isManagedKey(chave)) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "A ocorrência não possui imagem armazenada no S3.");
        }
        return criarImagemResponse(chave);
    }

    @Transactional
    public void excluirImagem(Integer id) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        String chaveAntiga = ocorrencia.getImagem();
        if (!s3StorageService.isManagedKey(chaveAntiga)) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "A ocorrência não possui imagem armazenada no S3.");
        }
        ocorrencia.setImagem(null);
        ocorrenciaRepository.save(ocorrencia);
        registrarLimpezaAposTransacao(null, chaveAntiga);
    }

    @Transactional
    public void excluir(Integer id) {
        Ocorrencia ocorrencia = buscarEntidadePorId(id);
        String chaveAntiga = ocorrencia.getImagem();
        ocorrenciaRepository.delete(ocorrencia);
        registrarLimpezaAposTransacao(null, chaveAntiga);
    }

    private void registrarLimpezaAposTransacao(String novaChave, String chaveAntiga) {
        if (!TransactionSynchronizationManager.isSynchronizationActive()) {
            throw new IllegalStateException("Não há transação ativa para coordenar o armazenamento da imagem.");
        }
        TransactionSynchronizationManager.registerSynchronization(new TransactionSynchronization() {
            @Override
            public void afterCompletion(int status) {
                if (status == STATUS_COMMITTED) {
                    if (s3StorageService.isManagedKey(chaveAntiga) && !java.util.Objects.equals(chaveAntiga, novaChave)) {
                        tentarExcluir(chaveAntiga);
                    }
                } else if (s3StorageService.isManagedKey(novaChave)) {
                    tentarExcluir(novaChave);
                }
            }
        });
    }

    private void tentarExcluir(String chave) {
        try {
            s3StorageService.delete(chave);
        } catch (RuntimeException exception) {
            log.error("Não foi possível excluir o objeto S3 '{}'. Será necessária limpeza posterior.", chave, exception);
        }
    }

    private ImagemResponseDto criarImagemResponse(String chave) {
        return new ImagemResponseDto(chave, s3StorageService.generatePresignedUrl(chave),
                s3StorageService.getPresignedUrlExpirationSeconds());
    }

    private void validarMaquina(Integer maquinaId) {
        if (maquinaId == null) return;
        try {
            maquinaClient.buscarPorId(maquinaId);
        } catch (FeignException.NotFound exception) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Máquina não encontrada.");
        } catch (FeignException exception) {
            throw new ResponseStatusException(HttpStatus.SERVICE_UNAVAILABLE,
                    "Não foi possível consultar o serviço de máquinas.");
        }
    }

    private Ocorrencia buscarEntidadePorId(Integer id) {
        return ocorrenciaRepository.findById(id).orElseThrow(() ->
                new ResponseStatusException(HttpStatus.NOT_FOUND, "Ocorrência não encontrada."));
    }

    private OcorrenciaResponseDto converterParaResponse(Ocorrencia ocorrencia) {
        return new OcorrenciaResponseDto(ocorrencia.getId(), ocorrencia.getUsuarioId(), ocorrencia.getMaquinaId(),
                ocorrencia.getTitulo(), ocorrencia.getTipo(), ocorrencia.getStatus(), ocorrencia.getDescricao(),
                ocorrencia.getImagem(), ocorrencia.getDataCriacao());
    }
}
