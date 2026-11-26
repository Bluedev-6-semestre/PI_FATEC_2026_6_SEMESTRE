package com.bluedev.occurrence_service.service;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import java.nio.file.Files;
import java.nio.file.Path;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.io.TempDir;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.mock.web.MockMultipartFile;
import org.springframework.web.server.ResponseStatusException;

class ImagemStorageServiceTest {

    private static final byte[] JPEG = {(byte) 0xFF, (byte) 0xD8, (byte) 0xFF, (byte) 0xE0, 0, 0x10, 'J', 'F', 'I', 'F', 0, 1};
    private static final byte[] PNG = {(byte) 0x89, 'P', 'N', 'G', 0x0D, 0x0A, 0x1A, 0x0A, 0, 0, 0, 0x0D};

    @TempDir
    Path pasta;

    private ImagemStorageService storage;

    @BeforeEach
    void criar() {
        storage = new ImagemStorageService(pasta.toString(), 1024);
    }

    private static MockMultipartFile arquivo(String nome, byte[] conteudo) {
        // O Content-Type enviado é ignorado de propósito: vale o conteúdo.
        return new MockMultipartFile("arquivo", nome, "application/octet-stream", conteudo);
    }

    @Test
    void salvaJpegPeloConteudoMesmoComExtensaoErrada() {
        storage.salvar(7, arquivo("foto.txt", JPEG));

        assertThat(pasta.resolve("7.jpg")).exists();
        assertThat(storage.carregar(7)).hasValueSatisfying(imagem ->
                assertThat(imagem.tipo()).isEqualTo(MediaType.IMAGE_JPEG));
    }

    @Test
    void trocarAFotoApagaAAnterior() {
        storage.salvar(7, arquivo("a.jpg", JPEG));
        storage.salvar(7, arquivo("b.png", PNG));

        assertThat(pasta.resolve("7.jpg")).doesNotExist();
        assertThat(pasta.resolve("7.png")).exists();
        assertThat(storage.carregar(7)).hasValueSatisfying(imagem ->
                assertThat(imagem.tipo()).isEqualTo(MediaType.IMAGE_PNG));
    }

    @Test
    void recusaArquivoQueNaoEImagem() {
        assertThatThrownBy(() -> storage.salvar(7, arquivo("foto.jpg", "não sou imagem".getBytes())))
                .isInstanceOfSatisfying(ResponseStatusException.class, erro ->
                        assertThat(erro.getStatusCode()).isEqualTo(HttpStatus.UNSUPPORTED_MEDIA_TYPE));
        assertThat(storage.carregar(7)).isEmpty();
    }

    @Test
    void recusaArquivoMaiorQueOLimite() {
        byte[] grande = new byte[2048];
        System.arraycopy(JPEG, 0, grande, 0, JPEG.length);

        assertThatThrownBy(() -> storage.salvar(7, arquivo("grande.jpg", grande)))
                .isInstanceOfSatisfying(ResponseStatusException.class, erro ->
                        assertThat(erro.getStatusCode()).isEqualTo(HttpStatus.PAYLOAD_TOO_LARGE));
    }

    @Test
    void recusaArquivoVazio() {
        assertThatThrownBy(() -> storage.salvar(7, arquivo("vazio.jpg", new byte[0])))
                .isInstanceOfSatisfying(ResponseStatusException.class, erro ->
                        assertThat(erro.getStatusCode()).isEqualTo(HttpStatus.BAD_REQUEST));
    }

    @Test
    void removerApagaSoAFotoDaquelaOcorrencia() throws Exception {
        storage.salvar(7, arquivo("a.jpg", JPEG));
        storage.salvar(70, arquivo("b.jpg", JPEG));

        storage.remover(7);

        assertThat(storage.carregar(7)).isEmpty();
        assertThat(storage.carregar(70)).isPresent();
        try (var arquivos = Files.list(pasta)) {
            assertThat(arquivos).noneMatch(p -> p.getFileName().toString().endsWith(".tmp"));
        }
    }

    @Test
    void removerSemFotoNaoFalha() {
        storage.remover(99);
        assertThat(storage.carregar(99)).isEmpty();
    }
}
