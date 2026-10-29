<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** RF01 – Gestão dos usuários e seus perfis (somente gerência). */
class UsuarioController extends Controller
{
    public function index()
    {
        return view('usuarios.index', ['usuarios' => User::orderBy('name')->get()]);
    }

    public function create()
    {
        return view('usuarios.form', ['usuario' => new User(['perfil' => User::PERFIL_ATENDENTE, 'ativo' => true])]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);
        $usuario = User::create($dados);
        Auditoria::registrar('criar', 'usuario', $usuario->id, ['email' => $usuario->email, 'perfil' => $usuario->perfil]);

        return redirect()->route('usuarios.index')->with('sucesso', "Usuário {$usuario->name} criado.");
    }

    public function edit(User $usuario)
    {
        return view('usuarios.form', compact('usuario'));
    }

    public function update(Request $request, User $usuario)
    {
        $dados = $this->validar($request, $usuario);

        if ($usuario->id === $request->user()->id && ($dados['perfil'] !== User::PERFIL_GERENTE || ! $dados['ativo'])) {
            return back()->withInput()->withErrors(['perfil' => 'Você não pode remover o seu próprio perfil de gerência nem se desativar.']);
        }

        if (empty($dados['password'])) {
            unset($dados['password']);
        }

        $usuario->update($dados);
        Auditoria::registrar('alterar', 'usuario', $usuario->id, ['email' => $usuario->email, 'campos' => array_values(array_diff(array_keys($usuario->getChanges()), ['password', 'updated_at']))]);

        return redirect()->route('usuarios.index')->with('sucesso', "Usuário {$usuario->name} atualizado.");
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $dados = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($usuario?->id)],
            'perfil' => ['required', Rule::in(array_keys(User::PERFIS))],
            'password' => [$usuario ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'ativo' => ['sometimes', 'boolean'],
        ], [], ['name' => 'nome', 'password' => 'senha']);
        $dados['ativo'] = $request->boolean('ativo');

        return $dados;
    }
}
