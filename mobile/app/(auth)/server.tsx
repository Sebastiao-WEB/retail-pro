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
import { router, useLocalSearchParams } from 'expo-router';
import { brand } from '@/src/theme/brand';
import { clearTokens } from '@/src/services/tokenStorage';
import { useAuthStore } from '@/src/store/authStore';
import { useSessionStore } from '@/src/store/sessionStore';
import {
  DEFAULT_TENANT_DOMAIN,
  extractSubdomainHint,
  getStoredApiBaseUrl,
  normalizeServerInput,
  saveApiBaseUrl,
} from '@/src/services/serverConfig';

export default function ServerConfigScreen() {
  const params = useLocalSearchParams<{ edit?: string }>();
  const allowBack = params.edit === '1';
  const [value, setValue] = useState('');
  const [preview, setPreview] = useState('');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    void (async () => {
      const current = await getStoredApiBaseUrl();
      if (current) {
        const hint = extractSubdomainHint(current) || current;
        setValue(hint);
        try {
          setPreview(normalizeServerInput(hint));
        } catch {
          setPreview(current);
        }
      }
      setLoading(false);
    })();
  }, []);

  function updateValue(next: string) {
    setValue(next);
    setError('');
    try {
      setPreview(next.trim() ? normalizeServerInput(next) : '');
    } catch {
      setPreview('');
    }
  }

  async function handleSave() {
    setError('');
    setSaving(true);
    try {
      const saved = await saveApiBaseUrl(value);
      setPreview(saved);
      if (allowBack) {
        await clearTokens().catch(() => undefined);
        await useSessionStore.getState().clear().catch(() => undefined);
        useAuthStore.setState({
          user: null,
          client: null,
          twoFactorToken: null,
          pendingClient: null,
          awaitingRegisterSelection: false,
          availableRegisters: [],
        });
      }
      router.replace('/login');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Não foi possível guardar o servidor.');
    } finally {
      setSaving(false);
    }
  }

  if (loading) {
    return (
      <View style={styles.loader}>
        <ActivityIndicator color={brand.gold} size="large" />
      </View>
    );
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

          <Text style={styles.title}>Servidor do cliente</Text>
          <Text style={styles.subtitle}>
            Cada cliente tem o seu subdomínio. Indique o subdomínio (ex.: padaria) ou a URL completa.
          </Text>

          <Text style={styles.label}>Subdomínio ou URL</Text>
          <TextInput
            value={value}
            onChangeText={updateValue}
            autoCapitalize="none"
            autoCorrect={false}
            autoComplete="off"
            keyboardType="url"
            placeholder="padaria"
            style={styles.input}
          />
          <Text style={styles.suffixHint}>.{DEFAULT_TENANT_DOMAIN}/api/v1</Text>

          {preview ? (
            <View style={styles.previewBox}>
              <Text style={styles.previewLabel}>API usada</Text>
              <Text style={styles.previewValue}>{preview}</Text>
            </View>
          ) : null}

          {error ? <Text style={styles.error}>{error}</Text> : null}

          <Pressable
            style={[styles.button, (!value.trim() || saving) && styles.buttonDisabled]}
            onPress={() => void handleSave()}
            disabled={saving || !value.trim()}
          >
            {saving ? (
              <ActivityIndicator color={brand.dark} />
            ) : (
              <Text style={styles.buttonText}>Guardar e continuar</Text>
            )}
          </Pressable>

          {allowBack ? (
            <Pressable onPress={() => router.back()}>
              <Text style={styles.linkMuted}>Cancelar</Text>
            </Pressable>
          ) : null}
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, backgroundColor: brand.dark },
  loader: {
    flex: 1,
    backgroundColor: brand.dark,
    justifyContent: 'center',
    alignItems: 'center',
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
    marginBottom: -40,
  },
  logo: {
    width: 280,
    height: 120,
    top: -20,
  },
  title: {
    fontSize: 20,
    fontWeight: '700',
    color: brand.dark,
    marginTop: 8,
  },
  subtitle: {
    fontSize: 13,
    color: brand.muted,
    lineHeight: 18,
    marginBottom: 8,
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
  suffixHint: {
    fontSize: 12,
    color: brand.muted,
    marginTop: -2,
  },
  previewBox: {
    marginTop: 8,
    backgroundColor: brand.background,
    borderRadius: 10,
    padding: 12,
    gap: 4,
  },
  previewLabel: {
    fontSize: 11,
    fontWeight: '600',
    color: brand.muted,
  },
  previewValue: {
    fontSize: 13,
    fontWeight: '600',
    color: brand.dark,
  },
  button: {
    marginTop: 16,
    backgroundColor: brand.gold,
    borderRadius: 10,
    paddingVertical: 14,
    alignItems: 'center',
  },
  buttonDisabled: { opacity: 0.5 },
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
  linkMuted: {
    textAlign: 'center',
    color: brand.muted,
    marginTop: 12,
  },
});
