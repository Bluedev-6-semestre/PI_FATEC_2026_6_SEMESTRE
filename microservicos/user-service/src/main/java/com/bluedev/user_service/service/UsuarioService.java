package com.bluedev.user_service.service;

import com.bluedev.user_service.dto.*;
import com.bluedev.user_service.entity.Usuario;
import com.bluedev.user_service.repository.UsuarioRepository;

import lombok.RequiredArgsConstructor;

import org.springframework.http.HttpStatus;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@Service
@RequiredArgsConstructor
public class UsuarioService {

    private final UsuarioRepository usuarioRepository;
    private final PasswordEncoder passwordEncoder;

    public UsuarioResponseDto cadastrar(MantemUsuarioRequestDto request) {

        if (request.senha() == null || request.senha().isBlank()) {
            throw new ResponseStatusException(
                    HttpStatus.BAD_REQUEST,
                    "A senha é obrigatória."
            );
        }

        if (usuarioRepository.existsByUsuario(request.usuario())) {
            throw new ResponseStatusException(
                    HttpStatus.CONFLICT,
                    "Nome de usuário já cadastrado."
            );
        }

        Usuario usuario = new Usuario();

        usuario.setUsuario(request.usuario());
        usuario.setSenha(passwordEncoder.encode(request.senha()));
        usuario.setTipoUsuario(request.tipoUsuario());

        usuario = usuarioRepository.save(usuario);

        return converterParaResponse(usuario);
    }

    public List<ListarUsuarioResponseDto> listarTodos() {

        return usuarioRepository.findAll()
                .stream()
                .map(usuario -> new ListarUsuarioResponseDto(
                        usuario.getId(),
                        usuario.getUsuario(),
                        usuario.getTipoUsuario()
                ))
                .toList();
    }

    public UsuarioResponseDto buscarPorId(Integer id) {

        Usuario usuario = buscarUsuario(id);

        return converterParaResponse(usuario);
    }

    public UsuarioResponseDto atualizar(
            Integer id,
            MantemUsuarioRequestDto request
    ) {

        Usuario usuario = buscarUsuario(id);

        if (usuarioRepository.existsByUsuarioAndIdNot(
                request.usuario(), id)) {

            throw new ResponseStatusException(
                    HttpStatus.CONFLICT,
                    "Nome de usuário já cadastrado."
            );
        }

        usuario.setUsuario(request.usuario());
        usuario.setTipoUsuario(request.tipoUsuario());

        if (request.senha() != null && !request.senha().isBlank()) {
            usuario.setSenha(passwordEncoder.encode(request.senha()));
        }

        usuario = usuarioRepository.save(usuario);

        return converterParaResponse(usuario);
    }

    public void excluir(Integer id) {

        Usuario usuario = buscarUsuario(id);

        usuarioRepository.delete(usuario);
    }

    public LoginResponseDto login(LoginRequestDto request) {

        Usuario usuario = usuarioRepository.findByUsuario(
                request.usuario()
        ).orElseThrow(() -> new ResponseStatusException(
                HttpStatus.UNAUTHORIZED,
                "Usuário ou senha inválidos."
        ));

        if (!passwordEncoder.matches(
                request.senha(),
                usuario.getSenha()
        )) {
            throw new ResponseStatusException(
                    HttpStatus.UNAUTHORIZED,
                    "Usuário ou senha inválidos."
            );
        }

        return new LoginResponseDto(
                usuario.getId(),
                usuario.getUsuario(),
                usuario.getTipoUsuario()
        );
    }

    private Usuario buscarUsuario(Integer id) {

        return usuarioRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(
                        HttpStatus.NOT_FOUND,
                        "Usuário não encontrado."
                ));
    }

    private UsuarioResponseDto converterParaResponse(Usuario usuario) {

        return new UsuarioResponseDto(
                usuario.getId(),
                usuario.getUsuario(),
                usuario.getTipoUsuario()
        );
    }
}