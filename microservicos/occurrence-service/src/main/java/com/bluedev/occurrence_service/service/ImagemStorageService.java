package com.bluedev.occurrence_service.service;

import java.io.IOException;
import java.io.InputStream;
import java.nio.file.DirectoryStream;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.util.Arrays;
import java.util.Optional;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.stereotype.Service;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;

/**
 * Guarda as fotos das ocorrências em disco, uma por ocorrência
 * ({id}.jpg, {id}.png ou {id}.webp). No Docker a pasta fica num volume.
 *
 * O tipo é conferido pelo conteúdo do arquivo (assinatura), não pela
 * extensão nem pelo Content-Type enviado.
 */
@Service
public class ImagemStorageService {

    public record Imagem(Path arquivo, MediaType tipo) {}

    private enum Formato {
        JPEG("jpg", MediaType.IMAGE_JPEG),
        PNG("png", MediaType.IMAGE_PNG),
        WEBP("webp", MediaType.parseMediaType("image/webp"));

        final String extensao;
        final MediaType tipo;

        Formato(String extensao, MediaType tipo) {
            this.extensao = extensao;
            this.tipo = tipo;
        }
    }

    private final Path diretorio;
    private final long tamanhoMaximo;

    public ImagemStorageService(
            @Value("${ocorrencias.imagens.diretorio:uploads/ocorrencias}") String diretorio,
            @Value("${ocorrencias.imagens.tamanho-maximo:5242880}") long tamanhoMaximo) {
        this.diretorio = Path.of(diretorio).toAbsolutePath().normalize();
        this.tamanhoMaximo = tamanhoMaximo;
    }

    public void salvar(Integer ocorrenciaId, MultipartFile arquivo) {
        if (arquivo == null || arquivo.isEmpty()) {
            throw new ResponseStatusException(
                    HttpStatus.BAD_REQUEST, "Envie um arquivo de imagem.");
        }
        if (arquivo.getSize() > tamanhoMaximo) {
            throw new ResponseStatusException(
                    HttpStatus.PAYLOAD_TOO_LARGE, "A imagem deve ter no máximo 5 MB.");
        }

        byte[] cabecalho;
        try (InputStream entrada = arquivo.getInputStream()) {
            cabecalho = entrada.readNBytes(12);
        } catch (IOException exception) {
            throw new ResponseStatusException(
                    HttpStatus.BAD_REQUEST, "Não foi possível ler a imagem enviada.");
        }
        Formato formato = detectar(cabecalho).orElseThrow(() -> new ResponseStatusException(
                HttpStatus.UNSUPPORTED_MEDIA_TYPE, "Envie uma imagem JPEG, PNG ou WebP."));

        try {
            Files.createDirectories(diretorio);
            Path temporario = Files.createTempFile(diretorio, ocorrenciaId + "-", ".tmp");
            try (InputStream entrada = arquivo.getInputStream()) {
                Files.copy(entrada, temporario, StandardCopyOption.REPLACE_EXISTING);
            }
            remover(ocorrenciaId);
            Files.move(temporario, caminho(ocorrenciaId, formato), StandardCopyOption.REPLACE_EXISTING);
        } catch (IOException exception) {
            throw new ResponseStatusException(
                    HttpStatus.INTERNAL_SERVER_ERROR, "Não foi possível salvar a imagem.");
        }
    }

    public Optional<Imagem> carregar(Integer ocorrenciaId) {
        for (Formato formato : Formato.values()) {
            Path arquivo = caminho(ocorrenciaId, formato);
            if (Files.isRegularFile(arquivo)) {
                return Optional.of(new Imagem(arquivo, formato.tipo));
            }
        }
        return Optional.empty();
    }

    /** Apaga a foto da ocorrência, se houver. */
    public void remover(Integer ocorrenciaId) {
        if (!Files.isDirectory(diretorio)) {
            return;
        }
        try (DirectoryStream<Path> fotos =
                     Files.newDirectoryStream(diretorio, ocorrenciaId + ".*")) {
            for (Path foto : fotos) {
                Files.deleteIfExists(foto);
            }
        } catch (IOException exception) {
            throw new ResponseStatusException(
                    HttpStatus.INTERNAL_SERVER_ERROR, "Não foi possível remover a imagem.");
        }
    }

    private Path caminho(Integer ocorrenciaId, Formato formato) {
        return diretorio.resolve(ocorrenciaId + "." + formato.extensao);
    }

    private static Optional<Formato> detectar(byte[] b) {
        if (b.length >= 3 && (b[0] & 0xFF) == 0xFF && (b[1] & 0xFF) == 0xD8 && (b[2] & 0xFF) == 0xFF) {
            return Optional.of(Formato.JPEG);
        }
        byte[] png = {(byte) 0x89, 'P', 'N', 'G', 0x0D, 0x0A, 0x1A, 0x0A};
        if (b.length >= 8 && Arrays.equals(Arrays.copyOf(b, 8), png)) {
            return Optional.of(Formato.PNG);
        }
        if (b.length >= 12
                && b[0] == 'R' && b[1] == 'I' && b[2] == 'F' && b[3] == 'F'
                && b[8] == 'W' && b[9] == 'E' && b[10] == 'B' && b[11] == 'P') {
            return Optional.of(Formato.WEBP);
        }
        return Optional.empty();
    }
}
