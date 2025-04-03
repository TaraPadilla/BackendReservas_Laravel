<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Traits\LogTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClienteController extends Controller
{
    use LogTrait;

    public function index()
    {
        try {
            $this->logInfo('Obteniendo lista de clientes');
            $clientes = Cliente::all();
            $this->logInfo('Lista de clientes obtenida', ['total' => $clientes->count()]);
            return $clientes;
        } catch (\Exception $e) {
            $this->logError('Error al obtener lista de clientes', $e);
            return response()->json(['message' => 'Error al obtener los clientes'], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $this->logInfo('Iniciando creación de cliente', $request->all());

            $validated = $request->validate([
                'nombre' => 'required|string|max:100',
                'email' => 'required|email|max:100',
                'telefono' => 'required|string|max:20',
                'preferencias' => 'nullable|string'
            ]);

            // Buscar si existe un cliente con el mismo email
            $clienteExistente = Cliente::where('email', $validated['email'])->first();

            if ($clienteExistente) {
                $this->logInfo('Cliente existente encontrado', ['cliente_id' => $clienteExistente->id]);
                return response()->json($clienteExistente);
            }

            // Si no existe, crear nuevo cliente
            $cliente = Cliente::create($validated);

            $this->logInfo('Cliente creado exitosamente', ['cliente_id' => $cliente->id]);

            return response()->json($cliente, 201);
        } catch (\Exception $e) {
            $this->logError('Error al crear cliente', $e);
            return response()->json(['message' => 'Error al crear el cliente'], 500);
        }
    }

    public function show(Cliente $cliente)
    {
        try {
            $this->logInfo('Obteniendo detalles de cliente', ['cliente_id' => $cliente->id]);
            return $cliente->load('reservas');
        } catch (\Exception $e) {
            $this->logError('Error al obtener detalles de cliente', $e);
            return response()->json(['message' => 'Error al obtener los detalles del cliente'], 500);
        }
    }

    public function update(Request $request, Cliente $cliente)
    {
        try {
            $this->logInfo('Iniciando actualización de cliente', [
                'cliente_id' => $cliente->id,
                'datos' => $request->all()
            ]);

            $validated = $request->validate([
                'nombre' => 'string|max:100',
                'email' => 'email|max:100|unique:clientes,email,' . $cliente->id,
                'telefono' => 'string|max:20',
                'preferencias' => 'nullable|string'
            ]);

            $cliente->update($validated);

            $this->logInfo('Cliente actualizado exitosamente', ['cliente_id' => $cliente->id]);

            return response()->json($cliente);
        } catch (\Exception $e) {
            $this->logError('Error al actualizar cliente', $e);
            return response()->json(['message' => 'Error al actualizar el cliente'], 500);
        }
    }

    public function destroy(Cliente $cliente)
    {
        try {
            $this->logInfo('Iniciando eliminación de cliente', ['cliente_id' => $cliente->id]);
            $cliente->delete();
            $this->logInfo('Cliente eliminado exitosamente', ['cliente_id' => $cliente->id]);
            return response()->json(null, 204);
        } catch (\Exception $e) {
            $this->logError('Error al eliminar cliente', $e);
            return response()->json(['message' => 'Error al eliminar el cliente'], 500);
        }
    }
} 