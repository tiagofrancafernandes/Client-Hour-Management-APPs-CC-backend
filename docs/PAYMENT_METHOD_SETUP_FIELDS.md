# Payment Method Setup Fields

## Overview

Este sistema permite que administradores configurem dados sensíveis (chaves de API, credenciais) em métodos de pagamento sem que clientes vejam essas informações.

## Arquitetura

### Backend

- **PaymentMethodConfig**: Modelo que armazena `setup_fields` como JSON
- **AbstractPaymentMethod**: Classe base que define `setupFieldRules()` e `setupFieldDefaultValues()`
- **PaymentMethodSetupValidator**: Serviço que valida dados de setup
- **PaymentMethodConfigController**: Controller que gerencia CRUD
- **UpdatePaymentMethodConfigRequest**: Form request com validação customizada

### Frontend

- **usePaymentMethodSetup**: Composable que gerencia estado de setup fields
- **CPaymentMethodSetupFields**: Componente que renderiza formulário
- **CPaymentMethodPixOfflineDisplay**: Componente que exibe QR code + copia e cola (PIX Offline específico)
- **CPaymentInstructionsEditModal**: Modal integrado com Setup button

## Como Usar

### Backend - Definir Campos de Setup

```php
class MinhaPaymentMethodClass extends AbstractPaymentMethod
{
    public function setupFieldRules(): array
    {
        return [
            [
                'label' => 'API Key',
                'name' => 'api_key',
                'required' => true,
                'type' => 'string',
                'min' => 10,
                'max' => 100,
            ],
            [
                'label' => 'Currency',
                'name' => 'currency',
                'required' => false,
                'type' => 'select',
                'options' => [
                    ['label' => 'USD', 'value' => 'USD'],
                    ['label' => 'BRL', 'value' => 'BRL'],
                ],
            ],
        ];
    }

    public function setupFieldDefaultValues(): array
    {
        return [
            'currency' => 'BRL',
        ];
    }
}
```

### Frontend - Editar Setup Fields

Os campos aparecem automaticamente no formulário de edição quando o método define `setupFieldRules()`.

### PIX Offline - QR Code Display

Para PIX Offline configurado, o sistema automaticamente exibe:
- QR Code (base64)
- Campo "Copia e Cola" com o PIX key
- Botão Copy para clipboard

## Validação

A validação ocorre em dois níveis:

1. **Form Request** (`UpdatePaymentMethodConfigRequest`): Validação de estrutura
2. **Service** (`PaymentMethodSetupValidator`): Validação de tipo e regras

## Testes

### Backend

```bash
php artisan test tests/Feature/Api/SetupFieldsTest.php
php artisan test tests/Unit/Services/PaymentMethodSetupValidatorTest.php
php artisan test tests/Feature/Api/PaymentMethodConfigControllerTest.php
```

### Frontend

```bash
npm run test:e2e
```

## Status de Completude

- `PaymentMethodConfig::isSetupComplete()`: Verifica se todos os campos obrigatórios foram preenchidos
- Retorna `true` se não há campos de setup ou todos obrigatórios foram preenchidos
- Retorna `false` se há campos obrigatórios faltando

## API Endpoints

### GET /api/admin/payment-method-configs/{id}

Retorna configuração com:
- `setup_field_rules` - Regras de validação
- `setup_field_defaults` - Valores padrão
- `is_setup_complete` - Flag de completude

### PUT /api/admin/payment-method-configs/{id}

Aceita:
- `setup_fields` - Array com dados de setup
- `instructions` - Array com instruções (campo existente)

Valida usando PaymentMethodSetupValidator e retorna status de completude.

## Implementação Específica: PIX Offline

O método PIX Offline requer 3 campos:
1. `pix_key` - Chave PIX (obrigatório, 4-25 caracteres)
2. `receiver_name` - Nome do recebedor (obrigatório, 4-25 caracteres)
3. `receiver_city_name` - Cidade (padrão: São Paulo)

Quando configurado, o frontend exibe:
- QR Code gerado a partir do pix_key
- Campo "Copia e Cola" com o pix_key
- Botão Copy para facilitar cópia

## Migration & Database

A migration adiciona:
- Coluna `setup_fields` (JSON, nullable) na tabela `payment_method_configs`
- Posicionada após `instructions`
- Rollback suportado
