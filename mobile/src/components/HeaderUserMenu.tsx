import { useState } from 'react';
import { Modal, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { SymbolView } from 'expo-symbols';
import { useConfirmLogout } from '@/src/hooks/useConfirmLogout';
import { useAuthStore } from '@/src/store/authStore';
import { brand } from '@/src/theme/brand';

export default function HeaderUserMenu() {
  const insets = useSafeAreaInsets();
  const { user } = useAuthStore();
  const confirmLogout = useConfirmLogout();
  const [open, setOpen] = useState(false);

  function fechar() {
    setOpen(false);
  }

  function handleLogout() {
    fechar();
    confirmLogout();
  }

  const nome = user?.name || user?.username || 'Utilizador';

  return (
    <>
      <Pressable
        onPress={() => setOpen(true)}
        style={styles.trigger}
        accessibilityRole="button"
        accessibilityLabel="Menu do utilizador"
      >
        <SymbolView
          name={{ ios: 'person.crop.circle', android: 'account_circle', web: 'account_circle' }}
          tintColor="#fff"
          size={26}
        />
      </Pressable>

      <Modal visible={open} transparent animationType="fade" onRequestClose={fechar}>
        <Pressable style={[styles.backdrop, { paddingTop: insets.top + 48 }]} onPress={fechar}>
          <Pressable style={styles.menu} onPress={(event) => event.stopPropagation()}>
            <Text style={styles.userName} numberOfLines={2}>
              {nome}
            </Text>
            {user?.username && user.username !== nome ? (
              <Text style={styles.userMeta}>@{user.username}</Text>
            ) : null}

            <View style={styles.divider} />

            <Pressable style={styles.logoutButton} onPress={handleLogout}>
              <Text style={styles.logoutText}>Sair</Text>
            </Pressable>
          </Pressable>
        </Pressable>
      </Modal>
    </>
  );
}

const styles = StyleSheet.create({
  trigger: {
    marginRight: 4,
    padding: 4,
  },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.35)',
    alignItems: 'flex-end',
    paddingRight: 12,
  },
  menu: {
    minWidth: 200,
    maxWidth: 260,
    backgroundColor: brand.white,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: brand.border,
    paddingVertical: 12,
    paddingHorizontal: 14,
    shadowColor: '#000',
    shadowOpacity: 0.15,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 6 },
    elevation: 8,
  },
  userName: {
    color: brand.dark,
    fontSize: 15,
    fontWeight: '700',
  },
  userMeta: {
    color: brand.muted,
    fontSize: 12,
    marginTop: 2,
  },
  divider: {
    height: 1,
    backgroundColor: brand.border,
    marginVertical: 10,
  },
  logoutButton: {
    backgroundColor: brand.danger,
    borderRadius: 8,
    paddingVertical: 10,
    alignItems: 'center',
  },
  logoutText: {
    color: brand.white,
    fontWeight: '700',
    fontSize: 14,
  },
});
