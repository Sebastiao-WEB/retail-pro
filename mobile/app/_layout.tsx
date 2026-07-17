import { useEffect, useState } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { Stack, useRouter, useSegments } from 'expo-router';
import { useAuthStore } from '@/src/store/authStore';
import { initDatabase } from '@/src/services/database/db';
import { hasConfiguredServer, hydrateServerConfigFromEnv } from '@/src/services/serverConfig';
import { brand } from '@/src/theme/brand';

export default function RootLayout() {
  const { hydrate, hydrated } = useAuthStore();
  const [bootReady, setBootReady] = useState(false);
  const [serverConfigured, setServerConfigured] = useState(false);

  useEffect(() => {
    void (async () => {
      try {
        await initDatabase();
        const url = await hydrateServerConfigFromEnv();
        setServerConfigured(Boolean(url));
      } catch {
        setServerConfigured(await hasConfiguredServer().catch(() => false));
      } finally {
        setBootReady(true);
      }
    })();
  }, []);

  useEffect(() => {
    if (bootReady) hydrate();
  }, [bootReady, hydrate]);

  if (!bootReady || !hydrated) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: brand.dark }}>
        <ActivityIndicator color={brand.gold} size="large" />
      </View>
    );
  }

  return (
    <>
      <AuthGate
        serverConfigured={serverConfigured}
        markServerConfigured={() => setServerConfigured(true)}
      />
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="(auth)" />
        <Stack.Screen name="(tabs)" />
      </Stack>
    </>
  );
}

function AuthGate({
  serverConfigured,
  markServerConfigured,
}: {
  serverConfigured: boolean;
  markServerConfigured: () => void;
}) {
  const router = useRouter();
  const segments = useSegments();
  const { user, client, twoFactorToken } = useAuthStore();

  useEffect(() => {
    const inAuth = segments[0] === '(auth)';
    const onServerScreen = segments[1] === 'server';

    void (async () => {
      const configured = serverConfigured || (await hasConfiguredServer());
      if (configured && !serverConfigured) {
        markServerConfigured();
      }

      if (!configured) {
        if (!onServerScreen) router.replace('/server');
        return;
      }

      if (!user && !inAuth) {
        router.replace(twoFactorToken ? '/two-factor' : '/login');
        return;
      }

      if (user && inAuth && !onServerScreen) {
        router.replace(client === 'pos' ? '/(tabs)/pos' : '/(tabs)');
      }
    })();
  }, [user, client, twoFactorToken, segments, router, serverConfigured, markServerConfigured]);

  return null;
}
