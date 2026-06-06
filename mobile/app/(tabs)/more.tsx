import { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { ApiError, fetchMe, updatePassword } from '@/src/api/authApi';
import FullScreenLoader from '@/src/components/FullScreenLoader';
import { useConfirmLogout } from '@/src/hooks/useConfirmLogout';
import { useAuthStore } from '@/src/store/authStore';
import type { AuthUser } from '@/src/types/auth';
import { brand } from '@/src/theme/brand';

function traduzirPerfil(role?: string) {
  if (role === 'ADMIN') return 'Administrador';
  if (role === 'MANAGER') return 'Gerente';
  if (role === 'CASHIER') return 'Operador';
  return role || '—';
}

function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.infoRow}>
      <Text style={styles.infoLabel}>{label}</Text>
      <Text style={styles.infoValue}>{value}</Text>
    </View>
  );
}

export default function MoreScreen() {
  const { user, client, hydrate } = useAuthStore();
  const confirmLogout = useConfirmLogout();
  const [profile, setProfile] = useState<AuthUser | null>(user);
  const [submitting, setSubmitting] = useState(false);
  const [senhaActual, setSenhaActual] = useState('');
  const [novaSenha, setNovaSenha] = useState('');
  const [confirmarSenha, setConfirmarSenha] = useState('');
  const [passwordError, setPasswordError] = useState('');

  const loadProfile = useCallback(async () => {
    try {
      const me = await fetchMe();
      setProfile(me.user);
    } catch {
      setProfile(user);
    }
  }, [user]);

  useEffect(() => {
    loadProfile();
  }, [loadProfile]);

  async function handleLogout() {
    await logout();
    router.replace('/login');
  }

  async function handleUpdatePassword() {
    setPasswordError('');

    if (!senhaActual || !novaSenha || !confirmarSenha) {
      setPasswordError('Preencha todos os campos de senha.');
      return;
    }

    if (novaSenha !== confirmarSenha) {
      setPasswordError('A confirmação da nova senha não coincide.');
      return;
    }

    setSubmitting(true);
    try {
      const response = await updatePassword(senhaActual, novaSenha, confirmarSenha);
      setSenhaActual('');
      setNovaSenha('');
      setConfirmarSenha('');
      await hydrate();
      await loadProfile();
      Alert.alert('Sucesso', response.message || 'Senha actualizada com sucesso.');
    } catch (err) {
      const message =
        err instanceof ApiError ? err.message : 'Não foi possível actualizar a senha.';
      setPasswordError(message);
    } finally {
      setSubmitting(false);
    }
  }

  const dados = profile ?? user;
  const caixas = dados?.registers?.map((item) => item.name || item.code).join(', ') || dados?.caixa_atribuido || '—';

  return (
    <>
      <KeyboardAvoidingView
        style={{ flex: 1 }}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <ScrollView style={styles.container} keyboardShouldPersistTaps="handled">
          <View style={styles.card}>
            <Text style={styles.sectionTitle}>Perfil</Text>
            <InfoRow label="Nome" value={dados?.name || '—'} />
            <InfoRow label="Utilizador" value={dados?.username || '—'} />
            <InfoRow label="Email" value={dados?.email || '—'} />
            <InfoRow label="Perfil" value={traduzirPerfil(dados?.role)} />
            <InfoRow label="Caixa(s)" value={caixas} />
            <InfoRow label="Sessão" value={client === 'admin' ? 'Mobile admin' : 'POS'} />
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionTitle}>Alterar senha</Text>
            <Text style={styles.passwordHint}>Mínimo 8 caracteres.</Text>
            <Text style={styles.fieldLabel}>Senha actual</Text>
            <TextInput
              value={senhaActual}
              onChangeText={setSenhaActual}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="••••••••"
              style={styles.input}
              editable={!submitting}
            />

            <Text style={styles.fieldLabel}>Nova senha</Text>
            <TextInput
              value={novaSenha}
              onChangeText={setNovaSenha}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="••••••••"
              style={styles.input}
              editable={!submitting}
            />

            <Text style={styles.fieldLabel}>Confirmar nova senha</Text>
            <TextInput
              value={confirmarSenha}
              onChangeText={setConfirmarSenha}
              secureTextEntry
              autoCapitalize="none"
              autoCorrect={false}
              placeholder="••••••••"
              style={styles.input}
              editable={!submitting}
            />

            {passwordError ? <Text style={styles.error}>{passwordError}</Text> : null}

            <Pressable
              style={[styles.saveButton, submitting && styles.saveButtonDisabled]}
              onPress={handleUpdatePassword}
              disabled={submitting}
            >
              <Text style={styles.saveButtonText}>Guardar nova senha</Text>
            </Pressable>
          </View>

          <Pressable style={styles.logout} onPress={confirmLogout}>
            <Text style={styles.logoutText}>Terminar sessão</Text>
          </Pressable>
        </ScrollView>
      </KeyboardAvoidingView>

      <FullScreenLoader visible={submitting} message="A guardar..." />
    </>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.background, padding: 16 },
  card: {
    backgroundColor: brand.white,
    borderRadius: 12,
    padding: 16,
    marginBottom: 12,
    borderWidth: 1,
    borderColor: brand.border,
    gap: 8,
  },
  sectionTitle: {
    fontSize: 16,
    fontWeight: '800',
    color: brand.dark,
    marginBottom: 4,
  },
  passwordHint: {
    color: brand.muted,
    fontSize: 12,
    marginBottom: 4,
  },
  infoRow: {
    gap: 2,
    paddingVertical: 4,
  },
  infoLabel: {
    color: brand.muted,
    fontSize: 12,
    fontWeight: '600',
  },
  infoValue: {
    color: brand.dark,
    fontSize: 14,
    fontWeight: '600',
  },
  fieldLabel: {
    color: brand.muted,
    fontSize: 12,
    fontWeight: '600',
    marginTop: 4,
  },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
    backgroundColor: brand.white,
  },
  saveButton: {
    marginTop: 8,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  saveButtonDisabled: {
    opacity: 0.6,
  },
  saveButtonText: {
    fontWeight: '700',
    color: brand.dark,
    fontSize: 15,
  },
  error: {
    color: brand.danger,
    fontSize: 13,
    marginTop: 4,
  },
  logout: {
    backgroundColor: brand.danger,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    marginBottom: 24,
  },
  logoutText: { color: brand.white, fontWeight: '700' },
});
