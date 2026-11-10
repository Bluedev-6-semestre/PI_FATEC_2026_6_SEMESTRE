package com.bluedev.occurrence_service.service;

import com.bluedev.occurrence_service.config.S3Properties;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.extension.ExtendWith;
import org.mockito.Mock;
import org.mockito.junit.jupiter.MockitoExtension;
import org.springframework.mock.web.MockMultipartFile;
import org.springframework.util.unit.DataSize;
import org.springframework.web.server.ResponseStatusException;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.presigner.S3Presigner;
import software.amazon.awssdk.services.s3.model.PutObjectRequest;
import software.amazon.awssdk.services.s3.model.PutObjectResponse;
import software.amazon.awssdk.core.sync.RequestBody;
import org.mockito.ArgumentCaptor;

import static org.junit.jupiter.api.Assertions.*;
import static org.mockito.Mockito.*;

@ExtendWith(MockitoExtension.class)
class S3StorageServiceTest {
    @Mock private S3Client s3Client;
    @Mock private S3Presigner s3Presigner;
    private S3StorageService storageService;

    @BeforeEach
    void setUp() {
        S3Properties properties = new S3Properties();
        properties.setBucket("bluedev-test-bucket");
        properties.setMaxFileSize(DataSize.ofMegabytes(1));
        storageService = new S3StorageService(s3Client, s3Presigner, properties);
    }

    @Test
    void deveRejeitarArquivoVazio() {
        MockMultipartFile file = new MockMultipartFile("arquivo", "vazio.png", "image/png", new byte[0]);
        ResponseStatusException exception = assertThrows(ResponseStatusException.class,
                () -> storageService.uploadOccurrenceImage(10, file));
        assertEquals(400, exception.getStatusCode().value());
        verifyNoInteractions(s3Client);
    }

    @Test
    void deveRejeitarTipoNaoPermitido() {
        MockMultipartFile file = new MockMultipartFile("arquivo", "documento.pdf", "application/pdf",
                new byte[]{1, 2, 3});
        ResponseStatusException exception = assertThrows(ResponseStatusException.class,
                () -> storageService.uploadOccurrenceImage(10, file));
        assertEquals(400, exception.getStatusCode().value());
        verifyNoInteractions(s3Client);
    }

    @Test
    void deveRejeitarArquivoMaiorQueOLimite() {
        byte[] content = new byte[1024 * 1024 + 1];
        MockMultipartFile file = new MockMultipartFile("arquivo", "imagem.png", "image/png", content);
        ResponseStatusException exception = assertThrows(ResponseStatusException.class,
                () -> storageService.uploadOccurrenceImage(10, file));
        assertEquals(413, exception.getStatusCode().value());
        verifyNoInteractions(s3Client);
    }

    @Test
    void deveRejeitarConteudoQueNaoCorrespondeAoMimeType() {
        MockMultipartFile file = new MockMultipartFile("arquivo", "imagem.png", "image/png",
                new byte[]{1, 2, 3, 4, 5, 6, 7, 8});
        ResponseStatusException exception = assertThrows(ResponseStatusException.class,
                () -> storageService.uploadOccurrenceImage(10, file));
        assertEquals(400, exception.getStatusCode().value());
        verifyNoInteractions(s3Client);
    }

    @Test
    void deveEnviarImagemValidaComChaveUuidNoPrefixoDaOcorrencia() {
        byte[] jpeg = new byte[]{(byte) 0xFF, (byte) 0xD8, (byte) 0xFF, 0x00};
        MockMultipartFile file = new MockMultipartFile("arquivo", "foto.jpg", "image/jpeg", jpeg);
        when(s3Client.putObject(any(PutObjectRequest.class), any(RequestBody.class)))
                .thenReturn(PutObjectResponse.builder().build());

        String key = storageService.uploadOccurrenceImage(42, file);

        assertTrue(key.matches("ocorrencias/42/[0-9a-fA-F-]{36}\\.jpg"));
        ArgumentCaptor<PutObjectRequest> requestCaptor = ArgumentCaptor.forClass(PutObjectRequest.class);
        verify(s3Client).putObject(requestCaptor.capture(), any(RequestBody.class));
        assertEquals("bluedev-test-bucket", requestCaptor.getValue().bucket());
        assertEquals(key, requestCaptor.getValue().key());
        assertEquals("image/jpeg", requestCaptor.getValue().contentType());
    }

    @Test
    void deveReconhecerSomenteChavesGerenciadasPeloServico() {
        assertTrue(storageService.isManagedKey("ocorrencias/10/0f7c2a0e-1b47-4e64-a7e4-ae1d4f9c2b0d.jpg"));
        assertFalse(storageService.isManagedKey("https://exemplo.com/imagem.jpg"));
        assertFalse(storageService.isManagedKey(null));
    }
}
