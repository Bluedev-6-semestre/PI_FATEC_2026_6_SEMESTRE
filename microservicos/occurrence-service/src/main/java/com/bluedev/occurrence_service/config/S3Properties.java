package com.bluedev.occurrence_service.config;

import org.springframework.boot.context.properties.ConfigurationProperties;
import org.springframework.util.unit.DataSize;

import java.time.Duration;

@ConfigurationProperties(prefix = "app.s3")
public class S3Properties {
    private String region = "us-east-1";
    private String bucket = "";
    private Duration presignedUrlExpiration = Duration.ofMinutes(15);
    private DataSize maxFileSize = DataSize.ofMegabytes(5);

    public String getRegion() { return region; }
    public void setRegion(String region) { this.region = region; }
    public String getBucket() { return bucket; }
    public void setBucket(String bucket) { this.bucket = bucket; }
    public Duration getPresignedUrlExpiration() { return presignedUrlExpiration; }
    public void setPresignedUrlExpiration(Duration value) { this.presignedUrlExpiration = value; }
    public DataSize getMaxFileSize() { return maxFileSize; }
    public void setMaxFileSize(DataSize value) { this.maxFileSize = value; }
}
