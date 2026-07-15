import { useState } from 'react';
import {
  ActivityIndicator,
  Image,
  KeyboardAvoidingView,
  Platform,
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

export default function LoginScreen() {
  const { user, hydrated, loading, login, twoFactorToken } = useAuthStore();
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');

  if (hydrated && user) {
    return <Redirect href="/(tabs)" />;
  }

  if (twoFactorToken) {
    return <Redirect href="/two-factor" />;
  }

  async function handleLogin() {
    setError('');
    try {
      await login(username.trim(), password);
      const state = useAuthStore.getState();
      if (state.twoFactorToken) {
        router.replace('/two-factor');
        return;
      }
      if (state.user) {
        router.replace('/(tabs)');
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

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.card}>
        <View style={styles.logoSlot}>
          <Image
            source={require('@/assets/retailpos-logo.png')}
            style={styles.logo}
            resizeMode="contain"
            accessibilityLabel="RetailPOS"
          />
        </View>

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

        {error ? <Text style={styles.error}>{error}</Text> : null}

        <Pressable style={styles.button} onPress={handleLogin} disabled={loading}>
          {loading ? (
            <ActivityIndicator color={brand.dark} />
          ) : (
            <Text style={styles.buttonText}>Entrar</Text>
          )}
        </Pressable>
      </View>
    </KeyboardAvoidingView>
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
});
