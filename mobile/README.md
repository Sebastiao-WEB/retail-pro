# RetailPro Mobile

App React Native (Expo) para **gerentes e administradores** — complemento ao POS Electron.

## Requisitos

- Node.js 20+
- Backend Laravel a correr (`backend/`)
- Conta ADMIN ou MANAGER

## Configuração

```bash
cd mobile
cp .env.example .env
npm install
```

Ajuste `EXPO_PUBLIC_API_URL` no `.env` (ex.: `http://192.168.1.10:8000/api/v1` para dispositivo físico).

## Executar

```bash
npm start
# ou
npm run android
npm run web
```

## Funcionalidades (MVP)

- Login admin (`POST /auth/admin-login`) com 2FA
- Dashboard (`GET /dashboard/summary`)
- Reversões pendentes (aprovar/rejeitar)
- Listagem de vendas
- Perfil e logout

## API

Endpoints novos para mobile:

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/auth/admin-login` | Login gerente/admin sem caixa |
| GET | `/auth/me` | Perfil + roles + permissões |
| GET | `/dashboard/summary` | KPIs e gráficos |

Credenciais de teste (seed): `admin` / `admin123456`
