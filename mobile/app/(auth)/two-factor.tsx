import { useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { Redirect, router } from 'expo-router';
import { ApiError } from '@/src/api/authApi';
import { useAuthStore } from '@/src/store/authStore';
import { brand } from '@/src/theme/brand';

function destinoAposLogin(client: 'admin' | 'pos' | null) {
  return client === 'pos' ? '/(tabs)/pos' : '/(tabs)';
}

export default function TwoFactorScreen() {
  const { user, client, hydrated, loading, twoFactorToken, confirmTwoFactor, cancelTwoFactor } = useAuthStore();
  const [code, setCode] = useState('');
  const [recoveryMode, setRecoveryMode] = useState(false);
  const [error, setError] = useState('');

  if (hydrated && user) {
    return <Redirect href={destinoAposLogin(client)} />;
  }

  if (!twoFactorToken) {
    return <Redirect href="/login" />;
  }

  async function handleConfirm() {
    setError('');
    try {
      await confirmTwoFactor(code.trim(), recoveryMode);
      router.replace(destinoAposLogin(useAuthStore.getState().client));
    } catch (err) {
      const message = err instanceof ApiError ? err.message : 'Código inválido.';
      setError(message);
    }
  }

  return (
    <View style={styles.container}>
      <View style={styles.card}>
        <Text style={styles.title}>Autenticação em dois factores</Text>
        <Text style={styles.subtitle}>
          {recoveryMode ? 'Introduza um código de recuperação.' : 'Introduza o código da aplicação autenticadora.'}
        </Text>

        <TextInput
          value={code}
          onChangeText={setCode}
          keyboardType={recoveryMode ? 'default' : 'number-pad'}
          autoCapitalize="none"
          placeholder={recoveryMode ? 'código de recuperação' : '000000'}
          style={styles.input}
        />

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <Pressable style={styles.button} onPress={handleConfirm} disabled={loading || !code.trim()}>
          {loading ? <ActivityIndicator color={brand.dark} /> : <Text style={styles.buttonText}>Confirmar</Text>}
        </Pressable>

        <Pressable onPress={() => setRecoveryMode((value) => !value)}>
          <Text style={styles.link}>
            {recoveryMode ? 'Usar código da app' : 'Usar código de recuperação'}
          </Text>
        </Pressable>

        <Pressable
          onPress={() => {
            cancelTwoFactor();
            router.replace('/login');
          }}
        >
          <Text style={styles.linkMuted}>Voltar ao login</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: brand.dark,
    justifyContent: 'center',
    padding: 24,
  },
  card: {
    backgroundColor: brand.white,
    borderRadius: 16,
    padding: 24,
    gap: 12,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: brand.dark,
  },
  subtitle: {
    color: brand.muted,
    marginBottom: 8,
  },
  input: {
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 12,
    fontSize: 18,
    textAlign: 'center',
    letterSpacing: 4,
  },
  button: {
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonText: {
    fontWeight: '700',
    color: brand.dark,
  },
  error: {
    color: brand.danger,
    fontSize: 13,
  },
  link: {
    textAlign: 'center',
    color: brand.dark,
    fontWeight: '600',
    marginTop: 8,
  },
  linkMuted: {
    textAlign: 'center',
    color: brand.muted,
    marginTop: 4,
  },
});
