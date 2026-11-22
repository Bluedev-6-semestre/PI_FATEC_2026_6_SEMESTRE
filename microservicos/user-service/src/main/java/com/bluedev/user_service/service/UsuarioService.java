package com.bluedev.user_service.service;

import com.bluedev.user_service.dto.*;
import com.bluedev.user_service.entity.Permissoes;
import com.bluedev.user_service.entity.TipoUsuario;
import com.bluedev.user_service.entity.Usuario;
import com.bluedev.user_service.repository.UsuarioRepository;

import lombok.RequiredArgsConstructor;

import org.springframework.http.HttpStatus;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;
import java.util.Locale;

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

        if (request.senha().length() < 6) {
            throw new ResponseStatusException(
                    HttpStatus.BAD_REQUEST,
                    "A senha deve ter de 6 a 72 caracteres."
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
        aplicarDados(usuario, request, null);

        usuario = usuarioRepository.save(usuario);

        return converterParaResponse(usuario);
    }

    public List<ListarUsuarioResponseDto> listar(
            String q,
            TipoUsuario tipo,
            Boolean ativo
    ) {

        String busca = q == null ? "" : q.trim().toLowerCase(Locale.ROOT);

        return usuarioRepository.findAll()
                .stream()
                .filter(u -> tipo == null || u.getTipoUsuario() == tipo)
                .filter(u -> ativo == null || ativo.equals(u.getAtivo()))
                .filter(u -> busca.isEmpty()
                        || u.getUsuario().toLowerCase(Locale.ROOT).contains(busca)
                        || (u.getNome() != null
                        && u.getNome().toLowerCase(Locale.ROOT).contains(busca)))
                .sorted((a, b) -> b.getId().compareTo(a.getId()))
                .map(u -> new ListarUsuarioResponseDto(
                        u.getId(),
                        u.getUsuario(),
                        u.getTipoUsuario(),
                        u.getNome(),
                        u.getAtivo(),
                        permissoesEfetivas(u),
                        u.getFuncionarioId()
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

        if (request.senha() != null && !request.senha().isBlank()
                && request.senha().length() < 6) {
            throw new ResponseStatusException(
                    HttpStatus.BAD_REQUEST,
                    "A senha deve ter de 6 a 72 caracteres."
            );
        }

        boolean serviraComoAdminAtivo =
                request.tipoUsuario() == TipoUsuario.ADMIN
                        && !Boolean.FALSE.equals(request.ativo());

        if (ehAdminAtivo(usuario) && !serviraComoAdminAtivo) {
            garantirOutroAdminAtivo();
        }

        usuario.setUsuario(request.usuario());

        if (request.senha() != null && !request.senha().isBlank()) {
            usuario.setSenha(passwordEncoder.encode(request.senha()));
        }

        aplicarDados(usuario, request, id);

        usuario = usuarioRepository.save(usuario);

        return converterParaResponse(usuario);
    }

    public void excluir(Integer id) {

        Usuario usuario = buscarUsuario(id);

        if (ehAdminAtivo(usuario)) {
            garantirOutroAdminAtivo();
        }

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
        ) || !Boolean.TRUE.equals(usuario.getAtivo())) {
            // Mesma mensagem para senha errada e conta inativa.
            throw new ResponseStatusException(
                    HttpStatus.UNAUTHORIZED,
                    "Usuário ou senha inválidos."
            );
        }

        return new LoginResponseDto(
                usuario.getId(),
                usuario.getUsuario(),
                usuario.getTipoUsuario(),
                usuario.getNome(),
                permissoesEfetivas(usuario)
        );
    }

    // Copia perfil, nome, situacao, permissoes e vinculo da requisicao para o usuario.
    private void aplicarDados(
            Usuario usuario,
            MantemUsuarioRequestDto request,
            Integer idAtual
    ) {

        usuario.setTipoUsuario(request.tipoUsuario());

        String nome = request.nome() == null ? "" : request.nome().trim();
        usuario.setNome(nome.isEmpty() ? null : nome);

        usuario.setAtivo(request.ativo() == null ? Boolean.TRUE : request.ativo());

        if (request.tipoUsuario() == TipoUsuario.ADMIN) {
            // Administrador tem acesso total; nao guarda permissoes individuais.
            usuario.setPermissoes(null);
        } else if (request.permissoes() == null) {
            usuario.setPermissoes(
                    idAtual == null
                            ? Permissoes.paraTexto(Permissoes.TODAS)
                            : usuario.getPermissoes()
            );
        } else {
            if (!Permissoes.saoValidas(request.permissoes())) {
                throw new ResponseStatusException(
                        HttpStatus.BAD_REQUEST,
                        "Permissão desconhecida."
                );
            }
            usuario.setPermissoes(Permissoes.paraTexto(request.permissoes()));
        }

        Integer funcionarioId = request.funcionarioId();

        if (funcionarioId != null
                && usuarioRepository.existsByFuncionarioIdAndIdNot(
                funcionarioId, idAtual == null ? 0 : idAtual)) {
            throw new ResponseStatusException(
                    HttpStatus.CONFLICT,
                    "Este funcionário já está vinculado a outro usuário."
            );
        }

        usuario.setFuncionarioId(funcionarioId);
    }

    private boolean ehAdminAtivo(Usuario usuario) {
        return usuario.getTipoUsuario() == TipoUsuario.ADMIN
                && Boolean.TRUE.equals(usuario.getAtivo());
    }

    // Impede ficar sem nenhum administrador ativo.
    private void garantirOutroAdminAtivo() {
        if (usuarioRepository.countByTipoUsuarioAndAtivoTrue(TipoUsuario.ADMIN) <= 1) {
            throw new ResponseStatusException(
                    HttpStatus.CONFLICT,
                    "É necessário manter pelo menos um administrador ativo."
            );
        }
    }

    private List<String> permissoesEfetivas(Usuario usuario) {
        if (usuario.getTipoUsuario() == TipoUsuario.ADMIN) {
            return Permissoes.TODAS;
        }
        return Permissoes.deTexto(usuario.getPermissoes());
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
                usuario.getTipoUsuario(),
                usuario.getNome(),
                usuario.getAtivo(),
                permissoesEfetivas(usuario),
                usuario.getFuncionarioId()
        );
    }
}
