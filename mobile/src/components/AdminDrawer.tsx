import { Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { router } from 'expo-router';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { SymbolView } from 'expo-symbols';
import { brand } from '@/src/theme/brand';

export type DrawerItem = {
  key: string;
  title: string;
  href: string;
  icon: {
    ios: string;
    android: string;
    web: string;
  };
};

type Props = {
  visible: boolean;
  items: DrawerItem[];
  userName?: string;
  onClose: () => void;
};

export default function AdminDrawer({ visible, items, userName, onClose }: Props) {
  const insets = useSafeAreaInsets();

  function navigate(href: string) {
    onClose();
    router.push(href as never);
  }

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.root}>
        <Pressable style={styles.backdrop} onPress={onClose} />
        <View style={[styles.panel, { paddingTop: insets.top + 12, paddingBottom: insets.bottom + 16 }]}>
          <View style={styles.header}>
            <Text style={styles.brand}>RetailPro</Text>
            <Text style={styles.subtitle}>Menu de gestão</Text>
            {userName ? <Text style={styles.user}>{userName}</Text> : null}
          </View>

          <ScrollView contentContainerStyle={styles.list}>
            {items.map((item) => (
              <Pressable key={item.key} style={styles.item} onPress={() => navigate(item.href)}>
                <View style={styles.iconWrap}>
                  <SymbolView
                    name={
                      {
                        ios: item.icon.ios,
                        android: item.icon.android,
                        web: item.icon.web,
                      } as never
                    }
                    tintColor={brand.dark}
                    size={22}
                  />
                </View>
                <Text style={styles.itemText}>{item.title}</Text>
              </Pressable>
            ))}
          </ScrollView>

          <Pressable style={styles.closeBtn} onPress={onClose}>
            <Text style={styles.closeText}>Fechar</Text>
          </Pressable>
        </View>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    flexDirection: 'row',
  },
  backdrop: {
    flex: 1,
    backgroundColor: 'rgba(15, 23, 42, 0.45)',
  },
  panel: {
    position: 'absolute',
    left: 0,
    top: 0,
    bottom: 0,
    width: '78%',
    maxWidth: 320,
    backgroundColor: brand.white,
    paddingHorizontal: 16,
    shadowColor: '#000',
    shadowOpacity: 0.2,
    shadowRadius: 16,
    shadowOffset: { width: 4, height: 0 },
    elevation: 12,
  },
  header: {
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: brand.border,
    marginBottom: 8,
  },
  brand: {
    fontSize: 20,
    fontWeight: '800',
    color: brand.dark,
  },
  subtitle: {
    marginTop: 2,
    fontSize: 13,
    color: brand.muted,
    fontWeight: '600',
  },
  user: {
    marginTop: 8,
    fontSize: 13,
    color: brand.dark,
    fontWeight: '600',
  },
  list: {
    gap: 4,
    paddingVertical: 8,
  },
  item: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 12,
    paddingVertical: 14,
    paddingHorizontal: 10,
    borderRadius: 10,
    backgroundColor: brand.background,
    marginBottom: 6,
  },
  iconWrap: {
    width: 28,
    alignItems: 'center',
  },
  itemText: {
    fontSize: 15,
    fontWeight: '700',
    color: brand.dark,
  },
  closeBtn: {
    marginTop: 8,
    borderWidth: 1,
    borderColor: brand.border,
    borderRadius: 10,
    paddingVertical: 12,
    alignItems: 'center',
  },
  closeText: {
    fontWeight: '700',
    color: brand.muted,
  },
});
