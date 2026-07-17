import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Redirect, router } from 'expo-router';
import { ApiError } from '@/src/api/authApi';
import { useAuthStore } from '@/src/store/authStore';
import type { LoginMode } from '@/src/store/authStore';
import { brand } from '@/src/theme/brand';
import { displayServerLabel, getStoredApiBaseUrl } from '@/src/services/serverConfig';

function destinoAposLogin(client: 'admin' | 'pos' | null) {
  return client === 'pos' ? '/(tabs)/pos' : '/(tabs)';
}

export default function LoginScreen() {
  const {
    user,
    client,
    hydrated,
    loading,
    login,
    twoFactorToken,
    loginMode,
    setLoginMode,
    awaitingRegisterSelection,
    availableRegisters,
    clearRegisterSelection,
  } = useAuthStore();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [registerCode, setRegisterCode] = useState('');
  const [error, setError] = useState('');
  const [serverLabel, setServerLabel] = useState('…');

  useEffect(() => {
    void getStoredApiBaseUrl().then((url) => setServerLabel(displayServerLabel(url)));
  }, []);

  if (hydrated && user) {
    return <Redirect href={destinoAposLogin(client)} />;
  }

  if (twoFactorToken) {
    return <Redirect href="/two-factor" />;
  }

  async function handleLogin(selectedRegisterCode?: string) {
    setError('');
    try {
      await login(username.trim(), password, {
        registerCode: selectedRegisterCode || registerCode.trim() || undefined,
      });
      const state = useAuthStore.getState();
      if (state.twoFactorToken) {
        router.replace('/two-factor');
        return;
      }
      if (state.awaitingRegisterSelection) {
        return;
      }
      if (state.user) {
        router.replace(destinoAposLogin(state.client));
      }
    } catch (err) {
      const message =
        err instanceof ApiError
          ? err.message
          : err instanceof Error
            ? err.message
            : 'Não foi possível iniciar sessão.';
      setError(message);
    }
  }

  function alterarModo(mode: LoginMode) {
    setLoginMode(mode);
    setError('');
    setRegisterCode('');
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView contentContainerStyle={styles.scroll} keyboardShouldPersistTaps="handled">
        <View style={styles.card}>
          <View style={styles.logoSlot}>
            <Image
              source={require('@/assets/retailpos-logo.png')}
              style={styles.logo}
              resizeMode="contain"
              accessibilityLabel="RetailPOS"
            />
          </View>

          <View style={styles.modeRow}>
            <Pressable
              style={[styles.modeButton, loginMode === 'pos' && styles.modeButtonActive]}
              onPress={() => alterarModo('pos')}
            >
              <Text style={[styles.modeButtonText, loginMode === 'pos' && styles.modeButtonTextActive]}>
                Vendas (POS)
              </Text>
            </Pressable>
            <Pressable
              style={[styles.modeButton, loginMode === 'admin' && styles.modeButtonActive]}
              onPress={() => alterarModo('admin')}
            >
              <Text style={[styles.modeButtonText, loginMode === 'admin' && styles.modeButtonTextActive]}>
                Gestão
              </Text>
            </Pressable>
          </View>

          <Text style={styles.hint}>
            {loginMode === 'pos'
              ? 'Para caixas e operadores de venda no balcão.'
              : 'Para gerentes e administradores.'}
          </Text>

          <Pressable style={styles.serverRow} onPress={() => router.push('/server?edit=1')}>
            <View style={{ flex: 1 }}>
              <Text style={styles.serverLabel}>Servidor</Text>
              <Text style={styles.serverValue} numberOfLines={1}>
                {serverLabel}
              </Text>
            </View>
            <Text style={styles.serverChange}>Alterar</Text>
          </Pressable>

          {awaitingRegisterSelection ? (
            <View style={styles.registerBlock}>
              <Text style={styles.registerTitle}>Selecione o caixa</Text>
              {availableRegisters.map((register) => (
                <Pressable
                  key={register.id}
                  style={styles.registerOption}
                  onPress={() => handleLogin(register.code)}
                  disabled={loading}
                >
                  <Text style={styles.registerName}>{register.name}</Text>
                  <Text style={styles.registerCode}>{register.code}</Text>
                </Pressable>
              ))}
              <Pressable onPress={clearRegisterSelection}>
                <Text style={styles.linkMuted}>Voltar</Text>
              </Pressable>
            </View>
          ) : (
            <>
              <Text style={styles.label}>Utilizador</Text>
              <TextInput
                value={username}
                onChangeText={setUsername}
                autoCapitalize="none"
                autoCorrect={false}
                placeholder="username ou email"
                style={styles.input}
              />

              <Text style={styles.label}>Palavra-passe</Text>
              <TextInput
                value={password}
                onChangeText={setPassword}
                secureTextEntry
                placeholder="••••••••"
                style={styles.input}
              />

              {loginMode === 'pos' ? (
                <>
                  <Text style={styles.label}>Código do caixa (opcional)</Text>
                  <TextInput
                    value={registerCode}
                    onChangeText={setRegisterCode}
                    autoCapitalize="characters"
                    autoCorrect={false}
                    placeholder="ex.: CX-01"
                    style={styles.input}
                  />
                </>
              ) : null}

              {error ? <Text style={styles.error}>{error}</Text> : null}

              <Pressable style={styles.button} onPress={() => handleLogin()} disabled={loading}>
                {loading ? (
                  <ActivityIndicator color={brand.dark} />
                ) : (
                  <Text style={styles.buttonText}>Entrar</Text>
                )}
              </Pressable>
            </>
          )}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: brand.dark,
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 24,
  },
  card: {
    backgroundColor: brand.white,
    borderRadius: 16,
    padding: 24,
    gap: 8,
  },
  logoSlot: {
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: -50,
  },
  logo: {
    width: 330,
    height: 150,
    top: -30,
  },
  modeRow: {
    flexDirection: 'row',
    gap: 8,
    marginTop: 8,
  },
  modeButton: {
    flex: 1,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingVertical: 10,
    alignItems: 'center',
  },
  modeButtonActive: {
    borderColor: brand.gold,
    backgroundColor: 'rgba(216, 182, 90, 0.15)',
  },
  modeButtonText: {
    fontWeight: '600',
    color: brand.muted,
    fontSize: 13,
  },
  modeButtonTextActive: {
    color: brand.dark,
  },
  hint: {
    fontSize: 12,
    color: brand.muted,
    marginBottom: 4,
  },
  serverRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: brand.background,
    marginBottom: 4,
  },
  serverLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: brand.muted,
  },
  serverValue: {
    fontSize: 13,
    fontWeight: '700',
    color: brand.dark,
  },
  serverChange: {
    fontWeight: '700',
    color: brand.gold,
    fontSize: 13,
  },
  label: {
    fontSize: 12,
    fontWeight: '600',
    color: brand.muted,
    marginTop: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 16,
  },
  button: {
    marginTop: 16,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonText: {
    fontWeight: '700',
    color: brand.dark,
    fontSize: 16,
  },
  error: {
    color: brand.danger,
    marginTop: 8,
    fontSize: 13,
  },
  registerBlock: {
    gap: 10,
    marginTop: 8,
  },
  registerTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: brand.dark,
  },
  registerOption: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    padding: 14,
    backgroundColor: brand.background,
  },
  registerName: {
    fontWeight: '700',
    color: brand.dark,
    fontSize: 15,
  },
  registerCode: {
    color: brand.muted,
    marginTop: 2,
    fontSize: 13,
  },
  linkMuted: {
    textAlign: 'center',
    color: brand.muted,
    marginTop: 8,
  },
});
