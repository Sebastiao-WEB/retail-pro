export type AuthUser = {
  id: string;
  name: string;
  username?: string;
  email?: string;
  role?: string;
  roles?: string[];
  permissions?: string[];
  caixa_atribuido?: string | null;
  registers?: Array<{ id: string; code: string; name: string }>;
  register?: {
    id: string;
    code: string;
    name: string;
    source_location?: SourceLocation | null;
  };
  source_location?: SourceLocation | null;
};

export type SourceLocation = {
  id: string;
  code: string;
  name: string;
};

export type LoginResponse = {
  access_token: string;
  refresh_token?: string;
  expires_in?: number;
  client?: 'admin' | 'pos';
  user?: AuthUser;
  requires_two_factor?: boolean;
  two_factor_token?: string;
  expires_in_2fa?: number;
};

export type MeResponse = {
  client: 'admin' | 'pos';
  user: AuthUser;
};
