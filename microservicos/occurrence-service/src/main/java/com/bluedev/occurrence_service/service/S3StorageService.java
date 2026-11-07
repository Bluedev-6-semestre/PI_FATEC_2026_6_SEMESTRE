package com.bluedev.occurrence_service.service;

import com.bluedev.occurrence_service.config.S3Properties;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.util.StringUtils;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;
import software.amazon.awssdk.core.exception.SdkException;
import software.amazon.awssdk.core.sync.RequestBody;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.DeleteObjectRequest;
import software.amazon.awssdk.services.s3.model.PutObjectRequest;
import software.amazon.awssdk.services.s3.presigner.S3Presigner;
import software.amazon.awssdk.services.s3.presigner.model.GetObjectPresignRequest;
import software.amazon.awssdk.services.s3.presigner.model.PresignedGetObjectRequest;
import software.amazon.awssdk.services.s3.model.GetObjectRequest;

import java.io.IOException;
import java.time.Duration;
import java.util.Locale;
import java.util.Map;
import java.util.UUID;

@Service
public class S3StorageService {
    private static final java.util.regex.Pattern MANAGED_KEY_PATTERN = java.util.regex.Pattern.compile(
            "^ocorrencias/\\d+/[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\\.(jpg|png|webp|gif)$"
    );
    private static final Map<String, String> ALLOWED_TYPES = Map.of(
            "image/jpeg", "jpg",
            "image/png", "png",
            "image/webp", "webp",
            "image/gif", "gif"
    );

    private final S3Client s3Client;
    private final S3Presigner s3Presigner;
    private final S3Properties properties;

    public S3StorageService(S3Client s3Client, S3Presigner s3Presigner, S3Properties properties) {
        this.s3Client = s3Client;
        this.s3Presigner = s3Presigner;
        this.properties = properties;
    }

    public String uploadOccurrenceImage(Integer occurrenceId, MultipartFile file) {
        validateConfiguration();
        validateFile(file);

        String contentType = normalizeContentType(file.getContentType());
        String extension = ALLOWED_TYPES.get(contentType);
        String key = "ocorrencias/" + occurrenceId + "/" + UUID.randomUUID() + "." + extension;

        try {
            PutObjectRequest request = PutObjectRequest.builder()
                    .bucket(properties.getBucket())
                    .key(key)
                    .contentType(contentType)
                    .contentLength(file.getSize())
                    .build();
            try (var inputStream = file.getInputStream()) {
                s3Client.putObject(request, RequestBody.fromInputStream(inputStream, file.getSize()));
            }
            return key;
        } catch (IOException | SdkException exception) {
            throw new ResponseStatusException(HttpStatus.SERVICE_UNAVAILABLE,
                    "Não foi possível enviar a imagem para o armazenamento.", exception);
        }
    }

    public String generatePresignedUrl(String key) {
        validateConfiguration();
        try {
            GetObjectRequest getObjectRequest = GetObjectRequest.builder()
                    .bucket(properties.getBucket())
                    .key(key)
                    .build();
            GetObjectPresignRequest presignRequest = GetObjectPresignRequest.builder()
                    .signatureDuration(properties.getPresignedUrlExpiration())
                    .getObjectRequest(getObjectRequest)
                    .build();
            PresignedGetObjectRequest presigned = s3Presigner.presignGetObject(presignRequest);
            return presigned.url().toString();
        } catch (SdkException exception) {
            throw new ResponseStatusException(HttpStatus.SERVICE_UNAVAILABLE,
                    "Não foi possível gerar o endereço temporário da imagem.", exception);
        }
    }

    public void delete(String key) {
        if (!isManagedKey(key)) return;
        validateConfiguration();
        try {
            s3Client.deleteObject(DeleteObjectRequest.builder()
                    .bucket(properties.getBucket())
                    .key(key)
                    .build());
        } catch (SdkException exception) {
            throw new IllegalStateException("Falha ao excluir objeto do S3: " + key, exception);
        }
    }

    public boolean isManagedKey(String key) {
        return StringUtils.hasText(key) && MANAGED_KEY_PATTERN.matcher(key).matches();
    }

    public long getPresignedUrlExpirationSeconds() {
        return properties.getPresignedUrlExpiration().toSeconds();
    }

    private void validateFile(MultipartFile file) {
        if (file == null || file.isEmpty() || file.getSize() <= 0) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "O arquivo enviado está vazio.");
        }
        if (file.getSize() > properties.getMaxFileSize().toBytes()) {
            throw new ResponseStatusException(HttpStatus.PAYLOAD_TOO_LARGE, "A imagem excede o tamanho máximo permitido.");
        }
        String contentType = normalizeContentType(file.getContentType());
        if (!ALLOWED_TYPES.containsKey(contentType)) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST,
                    "Tipo de imagem não permitido. Formatos aceitos: JPEG, PNG, WEBP e GIF.");
        }
        try {
            String detectedType = detectImageType(file.getBytes());
            if (!contentType.equals(detectedType)) {
                throw new ResponseStatusException(HttpStatus.BAD_REQUEST,
                        "O conteúdo do arquivo não corresponde ao tipo de imagem informado.");
            }
        } catch (IOException exception) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Não foi possível ler o arquivo enviado.", exception);
        }
    }

    private String detectImageType(byte[] bytes) {
        if (bytes.length >= 3 && (bytes[0] & 0xFF) == 0xFF && (bytes[1] & 0xFF) == 0xD8
                && (bytes[2] & 0xFF) == 0xFF) return "image/jpeg";
        if (bytes.length >= 8 && (bytes[0] & 0xFF) == 0x89 && bytes[1] == 0x50 && bytes[2] == 0x4E
                && bytes[3] == 0x47 && bytes[4] == 0x0D && bytes[5] == 0x0A && bytes[6] == 0x1A
                && bytes[7] == 0x0A) return "image/png";
        if (bytes.length >= 6) {
            String signature = new String(bytes, 0, 6, java.nio.charset.StandardCharsets.US_ASCII);
            if (signature.equals("GIF87a") || signature.equals("GIF89a")) return "image/gif";
        }
        if (bytes.length >= 12 && new String(bytes, 0, 4, java.nio.charset.StandardCharsets.US_ASCII).equals("RIFF")
                && new String(bytes, 8, 4, java.nio.charset.StandardCharsets.US_ASCII).equals("WEBP")) return "image/webp";
        return "";
    }

    private String normalizeContentType(String contentType) {
        return contentType == null ? "" : contentType.toLowerCase(Locale.ROOT).split(";")[0].trim();
    }

    private void validateConfiguration() {
        if (!StringUtils.hasText(properties.getBucket())) {
            throw new ResponseStatusException(HttpStatus.SERVICE_UNAVAILABLE,
                    "Armazenamento de imagens não configurado: defina AWS_S3_BUCKET.");
        }
        if (properties.getPresignedUrlExpiration() == null || properties.getPresignedUrlExpiration().isZero()
                || properties.getPresignedUrlExpiration().isNegative()
                || properties.getPresignedUrlExpiration().compareTo(Duration.ofDays(7)) > 0) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR,
                    "A expiração da URL pré-assinada deve estar entre 1 segundo e 7 dias.");
        }
    }
}
