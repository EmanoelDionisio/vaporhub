export interface Product {
  id: string;
  name: string;
  price: number;
  originalPrice?: number;
  image: string;
  rating: number;
  reviews: number;
  isNew?: boolean;
  isSale?: boolean;
  isCustomizable?: boolean;
  category: string;
  brand?: string;
}

const img = 'https://lh3.googleusercontent.com/aida-public/AB6AXuDpGPe5KeZiN7WjODGnEr8YFOq-FWxwq5XoVxewfDA74pNNSRfhdyoBHVtdKD03wu8LbReW1bzlLNjVShQdc33aSLxn3M-hJ3bIVhQDgFk49gG5qixpPge8kPBIaESzJCcvQ9WnqtuxXSu3XknirV19Cl_SIOHgUWqzDGPW-3ZvmxePcabbTQqprC4_2-eacW-isEDlbwsh94BhZXgYQU72kDNxEeA81Cfh3946VxrldYLd5P_6Qk9uFjr1n7iq_N0s9CmmSaYRZ0A';

export const products: Product[] = [
  {
    id: '1',
    name: 'Pod 5000 Puffs Manga Ice',
    price: 89.90,
    image: img,
    rating: 4.8,
    reviews: 124,
    isCustomizable: true,
    category: 'POD Descartável',
    brand: 'Vapor Hub'
  },
  {
    id: '2',
    name: 'Pod 1500 Puffs Uva Ice',
    price: 59.90,
    image: img,
    rating: 4.5,
    reviews: 45,
    isCustomizable: true,
    category: 'POD Descartável',
    brand: 'Vapor Hub'
  },
  {
    id: '3',
    name: 'Kit POD Recarregável',
    price: 129.00,
    originalPrice: 149.00,
    image: img,
    rating: 4.9,
    reviews: 210,
    isSale: true,
    category: 'POD Recarregável',
    brand: 'Vapor Hub'
  },
  {
    id: '4',
    name: 'e-Líquido Nic Salt 30ml',
    price: 45.00,
    image: img,
    rating: 4.7,
    reviews: 89,
    category: 'e-Líquidos',
    brand: 'Vapor Hub'
  },
  {
    id: '5',
    name: 'Coil 0.8 ohm',
    price: 24.90,
    image: img,
    rating: 4.8,
    reviews: 32,
    category: 'Acessórios',
    brand: 'Vapor Hub'
  },
  {
    id: '6',
    name: 'Vape Kit Starter',
    price: 189.00,
    image: img,
    rating: 4.2,
    reviews: 15,
    category: 'Vape',
    brand: 'Outra marca'
  },
  {
    id: '7',
    name: 'Nicotina oral 6mg',
    price: 32.90,
    image: img,
    rating: 4.6,
    reviews: 28,
    category: 'Nicotina oral',
    brand: 'Outra marca'
  },
  {
    id: '8',
    name: 'Vaporizador de ervas',
    price: 249.00,
    image: img,
    rating: 4.4,
    reviews: 42,
    category: 'Vaporizador de ervas',
    brand: 'Outra marca'
  }
];

export const categories = [
  { name: 'Vape', icon: 'bolt', image: img },
  { name: 'POD Descartável', icon: 'cloud', image: img },
  { name: 'POD Recarregável', icon: 'battery', image: img },
  { name: 'e-Líquidos', icon: 'droplet', image: img },
  { name: 'Acessórios', icon: 'settings', image: img },
  { name: 'Nicotina oral', image: img },
];
