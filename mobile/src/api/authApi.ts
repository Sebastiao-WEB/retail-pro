import { httpRequest, ApiError } from './httpClient';
import { saveTokens, clearTokens } from '../services/tokenStorage';
import type { LoginResponse, MeResponse } from '../types/auth';

export { ApiError };

export type RegisterOption = {
  id: string;
  code: string;
  name: string;
};

export type PosLoginResult =
  | LoginResponse
  | {
      requires_two_factor: true;
      two_factor_token: string;
      client: 'pos';
    }
  | {
      requires_register_selection: true;
      registers: RegisterOption[];
      message?: string;
    };

export async function posLogin(
  username: string,
  password: string,
  registerCode?: string,
): Promise<PosLoginResult> {
  try {
    const response = await httpRequest<LoginResponse>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({
        username,
        password,
        register_code: registerCode || undefined,
      }),
    });

    if (response.access_token) {
      await saveTokens(response.access_token, response.refresh_token);
    }

    return { ...response, client: response.client ?? 'pos' };
  } catch (error) {
    if (error instanceof ApiError && error.status === 422) {
      if (error.payload?.requires_two_factor) {
        return {
          requires_two_factor: true,
          two_factor_token: String(error.payload.two_factor_token ?? ''),
          client: 'pos',
        };
      }
      if (error.payload?.requires_register_selection) {
        const registers = Array.isArray(error.payload.registers) ? error.payload.registers : [];
        return {
          requires_register_selection: true,
          registers: registers as RegisterOption[],
          message: error.message,
        };
      }
    }
    throw error;
  }
}

export async function adminLogin(username: string, password: string) {
  try {
    const response = await httpRequest<LoginResponse>('/auth/admin-login', {
      method: 'POST',
      body: JSON.stringify({ username, password }),
    });

    if (response.access_token) {
      await saveTokens(response.access_token, response.refresh_token);
    }

    return response;
  } catch (error) {
    if (error instanceof ApiError && error.status === 422 && error.payload?.requires_two_factor) {
      return {
        requires_two_factor: true,
        two_factor_token: String(error.payload.two_factor_token ?? ''),
        client: 'admin' as const,
      };
    }
    throw error;
  }
}

export async function twoFactorChallenge(twoFactorToken: string, code?: string, recoveryCode?: string) {
  const response = await httpRequest<LoginResponse>('/auth/two-factor-challenge', {
    method: 'POST',
    body: JSON.stringify({
      two_factor_token: twoFactorToken,
      code: code ?? null,
      recovery_code: recoveryCode ?? null,
    }),
  });

  if (response.access_token) {
    await saveTokens(response.access_token, response.refresh_token);
  }

  return response;
}

export async function fetchMe() {
  return httpRequest<MeResponse>('/auth/me');
}

export async function updatePassword(
  currentPassword: string,
  password: string,
  passwordConfirmation: string,
) {
  return httpRequest<{ message: string }>('/auth/password', {
    method: 'PUT',
    body: JSON.stringify({
      current_password: currentPassword,
      password,
      password_confirmation: passwordConfirmation,
    }),
  });
}

export async function logout() {
  try {
    await httpRequest('/auth/logout', { method: 'POST' });
  } finally {
    await clearTokens();
  }
}
