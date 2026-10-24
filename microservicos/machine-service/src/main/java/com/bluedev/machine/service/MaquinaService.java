package com.bluedev.machine.service;

import com.bluedev.machine.dto.MaquinaResponse;
import com.bluedev.machine.repository.MaquinaRepository;
import org.springframework.stereotype.Service;

import java.util.List;

@Service
public class MaquinaService {

    private final MaquinaRepository maquinaRepository;

    public MaquinaService(MaquinaRepository maquinaRepository) {
        this.maquinaRepository = maquinaRepository;
    }

    public List<MaquinaResponse> listarTodas() {
        return maquinaRepository.findAll()
                .stream()
                .sorted((a, b) ->
                        a.getNome().compareToIgnoreCase(b.getNome()))
                .map(maquina -> new MaquinaResponse(
                        maquina.getId(),
                        maquina.getNome()
                ))
                .toList();
    }
}
