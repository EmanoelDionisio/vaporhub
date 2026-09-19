/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { Home } from '@/pages/Home';
import { Shop } from '@/pages/Shop';
import { ProductDetails } from '@/pages/ProductDetails';
import { PlaceholderPage } from '@/pages/Placeholder';

import { Cart } from '@/pages/Cart';
import { Checkout } from '@/pages/Checkout';
import { Auth } from '@/pages/Auth';
import { Contact } from '@/pages/Contact';
import { NotFound } from '@/pages/NotFound';

export default function App() {
  return (
    <Router>
      <Routes>
        <Route path="/" element={<Home />} />
        <Route path="/loja" element={<Shop />} />
        <Route path="/produto/:id" element={<ProductDetails />} />
        <Route path="/carrinho" element={<Cart />} />
        <Route path="/checkout" element={<Checkout />} />
        <Route path="/minha-conta" element={<Auth />} />
        <Route path="/contato" element={<Contact />} />
        <Route path="/acessorios" element={<PlaceholderPage title="Acessórios" />} />
        <Route path="/comunidade" element={<PlaceholderPage title="Comunidade" />} />
        
        {/* Fallback para 404 */}
        <Route path="*" element={<NotFound />} />
      </Routes>
    </Router>
  );
}
