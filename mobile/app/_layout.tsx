import { useEffect } from 'react';
import { ActivityIndicator, View } from 'react-native';
import { Stack, useRouter, useSegments } from 'expo-router';
import { useAuthStore } from '@/src/store/authStore';
import { brand } from '@/src/theme/brand';

export default function RootLayout() {
  const { hydrate, hydrated } = useAuthStore();

  useEffect(() => {
    hydrate();
  }, [hydrate]);

  if (!hydrated) {
    return (
      <View style={{ flex: 1, justifyContent: 'center', alignItems: 'center', backgroundColor: brand.dark }}>
        <ActivityIndicator color={brand.gold} size="large" />
      </View>
    );
  }

  return (
    <>
      <AuthGate />
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="(auth)" />
        <Stack.Screen name="(tabs)" />
      </Stack>
    </>
  );
}

function AuthGate() {
  const router = useRouter();
  const segments = useSegments();
  const { user, client, twoFactorToken } = useAuthStore();

  useEffect(() => {
    const inAuth = segments[0] === '(auth)';

    if (!user && !inAuth) {
      router.replace(twoFactorToken ? '/two-factor' : '/login');
      return;
    }

    if (user && inAuth) {
      router.replace(client === 'pos' ? '/(tabs)/pos' : '/(tabs)');
    }
  }, [user, client, twoFactorToken, segments, router]);

  return null;
}
