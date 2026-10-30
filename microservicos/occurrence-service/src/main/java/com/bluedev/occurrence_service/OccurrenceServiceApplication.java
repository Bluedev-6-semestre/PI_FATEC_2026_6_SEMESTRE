package com.bluedev.occurrence_service;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.cloud.openfeign.EnableFeignClients;

@SpringBootApplication
@EnableFeignClients
public class OccurrenceServiceApplication {

	public static void main(String[] args) {
		SpringApplication.run(OccurrenceServiceApplication.class, args);
	}
}