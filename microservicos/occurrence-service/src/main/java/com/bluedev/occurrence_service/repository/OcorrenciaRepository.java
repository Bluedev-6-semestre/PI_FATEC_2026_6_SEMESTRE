
package com.bluedev.occurrence_service.repository;

import com.bluedev.occurrence_service.entity.Ocorrencia;
import org.springframework.data.jpa.repository.JpaRepository;

public interface OcorrenciaRepository extends JpaRepository<Ocorrencia, Integer> {
}
