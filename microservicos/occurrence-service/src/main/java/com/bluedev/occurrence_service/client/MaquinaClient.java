
package com.bluedev.occurrence_service.client;

import org.springframework.cloud.openfeign.FeignClient;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;

@FeignClient(
        name = "machine-service",
        url = "${machine-service.url}"
)
public interface MaquinaClient {

    @GetMapping("/api/maquinas/{id}")
    void buscarPorId(@PathVariable("id") Integer id);
}
