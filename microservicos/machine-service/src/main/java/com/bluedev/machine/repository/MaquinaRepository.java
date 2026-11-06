package com.bluedev.machine.repository;

import com.bluedev.machine.entity.Maquina;
import org.springframework.data.jpa.repository.JpaRepository;

public interface MaquinaRepository
        extends JpaRepository<Maquina, Integer> {
}
