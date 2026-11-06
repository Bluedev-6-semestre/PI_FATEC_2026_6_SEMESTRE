
package com.bluedev.machine.service;

import com.bluedev.machine.dto.ListarMaquinaResponseDto;
import com.bluedev.machine.dto.MantemMaquinaRequestDto;
import com.bluedev.machine.dto.MaquinaResponseDto;
import com.bluedev.machine.entity.Maquina;
import com.bluedev.machine.repository.MaquinaRepository;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@Service
public class MaquinaService {

    private final MaquinaRepository maquinaRepository;

    public MaquinaService(MaquinaRepository maquinaRepository) {
        this.maquinaRepository = maquinaRepository;
    }

    public List<ListarMaquinaResponseDto> listarTodas() {
        return maquinaRepository.findAll()
                .stream()
                .sorted((a, b) ->
                        a.getNome().compareToIgnoreCase(b.getNome()))
                .map(maquina -> new ListarMaquinaResponseDto(
                        maquina.getId(),
                        maquina.getNome()
                ))
                .toList();
    }

    public MaquinaResponseDto buscarPorId(Integer id) {
        Maquina maquina = buscarEntidade(id);
        return paraResponse(maquina);
    }

    public MaquinaResponseDto criar(MantemMaquinaRequestDto request) {
        Maquina maquina = new Maquina();
        preencherDados(maquina, request);

        Maquina salva = maquinaRepository.save(maquina);
        return paraResponse(salva);
    }

    public MaquinaResponseDto atualizar(
            Integer id,
            MantemMaquinaRequestDto request) {

        Maquina maquina = buscarEntidade(id);
        preencherDados(maquina, request);

        Maquina atualizada = maquinaRepository.save(maquina);
        return paraResponse(atualizada);
    }

    public void excluir(Integer id) {
        Maquina maquina = buscarEntidade(id);
        maquinaRepository.delete(maquina);
    }

    private Maquina buscarEntidade(Integer id) {
        return maquinaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(
                        HttpStatus.NOT_FOUND,
                        "Máquina não encontrada: " + id
                ));
    }

    private void preencherDados(
            Maquina maquina,
            MantemMaquinaRequestDto request) {

        maquina.setUsuarioId(request.usuarioId());
        maquina.setNome(request.nome());
        maquina.setCodigo(request.codigo());
        maquina.setModelo(request.modelo());
        maquina.setFabricante(request.fabricante());
        maquina.setNumeroSerie(request.numeroSerie());
        maquina.setDataAquisicao(request.dataAquisicao());
        maquina.setLocalizacao(request.localizacao());
        maquina.setStatusMaquina(request.statusMaquina());
        maquina.setDescricao(request.descricao());
    }

    private MaquinaResponseDto paraResponse(Maquina maquina) {
        return new MaquinaResponseDto(
                maquina.getId(),
                maquina.getUsuarioId(),
                maquina.getNome(),
                maquina.getCodigo(),
                maquina.getModelo(),
                maquina.getFabricante(),
                maquina.getNumeroSerie(),
                maquina.getDataAquisicao(),
                maquina.getLocalizacao(),
                maquina.getStatusMaquina(),
                maquina.getDescricao()
        );
    }
}
