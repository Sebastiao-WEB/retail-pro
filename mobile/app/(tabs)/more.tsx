import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useAuthStore } from '@/src/store/authStore';
import { brand } from '@/src/theme/brand';
import { apiConfig } from '@/src/api/config';

export default function MoreScreen() {
  const { user, logout } = useAuthStore();

  async function handleLogout() {
    await logout();
    router.replace('/login');
  }

  return (
    <ScrollView style={styles.container}>
      <View style={styles.card}>
        <Text style={styles.name}>{user?.name}</Text>
        <Text style={styles.meta}>@{user?.username ?? user?.email}</Text>
        <Text style={styles.role}>{user?.role}</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.label}>API</Text>
        <Text style={styles.value}>{apiConfig.baseUrl}</Text>
      </View>

      {user?.permissions?.length ? (
        <View style={styles.card}>
          <Text style={styles.label}>Permissões ({user.permissions.length})</Text>
          <Text style={styles.permissions}>{user.permissions.slice(0, 8).join(' · ')}{user.permissions.length > 8 ? '…' : ''}</Text>
        </View>
      ) : null}

      <Pressable style={styles.logout} onPress={handleLogout}>
        <Text style={styles.logoutText}>Terminar sessão</Text>
      </Pressable>
    </ScrollView>
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
  },
  name: { fontSize: 20, fontWeight: '800', color: brand.dark },
  meta: { color: brand.muted, marginTop: 4 },
  role: {
    marginTop: 8,
    alignSelf: 'flex-start',
    backgroundColor: brand.dark,
    color: brand.gold,
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 999,
    overflow: 'hidden',
    fontWeight: '700',
    fontSize: 12,
  },
  label: { color: brand.muted, fontSize: 12, fontWeight: '600' },
  value: { color: brand.dark, marginTop: 4, fontSize: 13 },
  permissions: { color: brand.dark, marginTop: 6, fontSize: 12, lineHeight: 18 },
  logout: {
    backgroundColor: brand.danger,
    borderRadius: 12,
    paddingVertical: 14,
    alignItems: 'center',
    marginTop: 8,
  },
  logoutText: { color: brand.white, fontWeight: '700' },
});
