import { SymbolView } from 'expo-symbols';
import { Tabs, Redirect } from 'expo-router';
import { useAuthStore } from '@/src/store/authStore';
import { brand } from '@/src/theme/brand';

export default function TabLayout() {
  const { user } = useAuthStore();

  if (!user) {
    return <Redirect href="/login" />;
  }

  return (
    <Tabs
      screenOptions={{
        tabBarActiveTintColor: brand.gold,
        tabBarInactiveTintColor: brand.muted,
        headerStyle: { backgroundColor: brand.dark },
        headerTintColor: '#fff',
        tabBarStyle: { backgroundColor: brand.white },
      }}
    >
      <Tabs.Screen
        name="index"
        options={{
          title: 'Dashboard',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'chart.bar', android: 'bar_chart', web: 'bar_chart' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="reversals"
        options={{
          title: 'Reversões',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'arrow.uturn.left', android: 'undo', web: 'undo' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="sales"
        options={{
          title: 'Vendas',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'cart', android: 'shopping_cart', web: 'shopping_cart' }} tintColor={color} size={24} />
          ),
        }}
      />
      <Tabs.Screen
        name="more"
        options={{
          title: 'Conta',
          tabBarIcon: ({ color }) => (
            <SymbolView name={{ ios: 'person.crop.circle', android: 'person', web: 'person' }} tintColor={color} size={24} />
          ),
        }}
      />
    </Tabs>
  );
}
