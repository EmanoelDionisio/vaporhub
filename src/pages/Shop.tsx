import { useState } from 'react';
import { Layout } from '@/components/layout/Layout';
import { ProductCard } from '@/components/ui/ProductCard';
import { products } from '@/lib/data';
import { Filter, ChevronDown, Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Shop() {
  const [priceRange, setPriceRange] = useState(300);
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);

  const categories = ['POD Descartável', 'POD Recarregável', 'e-Líquidos', 'Vape', 'Acessórios'];

  const filteredProducts = products.filter(p => {
    if (selectedCategory && p.category !== selectedCategory) return false;
    if (p.price > priceRange) return false;
    return true;
  });

  return (
    <Layout>
      <div className="max-w-[1400px] mx-auto px-4 lg:px-10 py-8">
        <div className="flex items-center gap-2 mb-6 text-sm">
          <span className="text-text-muted">Início</span>
          <span className="text-text-muted">/</span>
          <span className="text-text-muted">Loja</span>
          <span className="text-text-muted">/</span>
          <span className="font-medium text-text-main">Todos os Produtos</span>
        </div>

        <div className="flex flex-col md:flex-row items-baseline justify-between mb-8">
          <h1 className="text-3xl font-extrabold text-text-main">Loja</h1>
          <div className="flex items-center gap-2 text-sm text-text-muted mt-4 md:mt-0">
            <span>Ordenar por:</span>
            <select className="bg-transparent border-none text-text-main font-medium cursor-pointer focus:ring-0 py-0">
              <option>Mais Populares</option>
              <option>Preço: Menor para Maior</option>
              <option>Preço: Maior para Menor</option>
              <option>Mais Recentes</option>
            </select>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
          {/* Filters Sidebar */}
          <aside className="hidden lg:block lg:col-span-1 space-y-6">
            <div className="bg-surface p-4 rounded-xl border border-border">
              <label className="flex items-center gap-3 cursor-pointer group">
                <div className="size-5 rounded border border-gray-300 flex items-center justify-center text-primary group-hover:border-primary transition-colors">
                  <Check className="size-3.5 opacity-0 group-hover:opacity-100" />
                </div>
                <span className="font-bold text-text-main group-hover:text-primary transition-colors">Apenas Exclusivos</span>
              </label>
            </div>

            <div className="border-b border-border pb-4">
              <details className="group" open>
                <summary className="flex justify-between items-center font-bold text-lg cursor-pointer list-none text-text-main mb-3">
                  <span>Categoria</span>
                  <ChevronDown className="size-5 transition-transform group-open:rotate-180" />
                </summary>
                <ul className="space-y-2 text-text-muted text-sm pl-2">
                  <li>
                    <button
                      onClick={() => setSelectedCategory(null)}
                      className={cn("hover:text-primary transition-colors w-full text-left", !selectedCategory && "text-primary font-bold")}
                    >
                      Todas as Categorias
                    </button>
                  </li>
                  {categories.map(cat => (
                    <li key={cat}>
                      <button
                        onClick={() => setSelectedCategory(cat)}
                        className={cn("hover:text-primary transition-colors w-full text-left", selectedCategory === cat && "text-primary font-bold")}
                      >
                        {cat}
                      </button>
                    </li>
                  ))}
                </ul>
              </details>
            </div>

            <div className="pb-4">
              <details className="group" open>
                <summary className="flex justify-between items-center font-bold text-lg cursor-pointer list-none text-text-main mb-3">
                  <span>Faixa de Preço</span>
                  <ChevronDown className="size-5 transition-transform group-open:rotate-180" />
                </summary>
                <div className="px-2">
                  <input
                    type="range"
                    min="0"
                    max="300"
                    value={priceRange}
                    onChange={(e) => setPriceRange(Number(e.target.value))}
                    className="w-full h-2 bg-border rounded-lg appearance-none cursor-pointer accent-primary mb-3"
                  />
                  <div className="flex justify-between text-sm text-text-main font-medium">
                    <span>R$ 0</span>
                    <span>R$ {priceRange}</span>
                  </div>
                </div>
              </details>
            </div>
          </aside>

          {/* Mobile Filter Toggle */}
          <div className="lg:hidden w-full">
            <button className="w-full flex items-center justify-center gap-2 bg-surface border border-border p-3 rounded-lg font-bold text-text-main">
              <Filter className="size-5" /> Filtros
            </button>
          </div>

          {/* Product Grid */}
          <div className="lg:col-span-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
              {filteredProducts.map((product) => (
                <ProductCard key={product.id} product={product} />
              ))}
            </div>

            {filteredProducts.length === 0 && (
              <div className="text-center py-20">
                <p className="text-text-muted text-lg">Nenhum produto encontrado com os filtros selecionados.</p>
                <button
                  onClick={() => { setPriceRange(300); setSelectedCategory(null); }}
                  className="mt-4 text-primary font-bold hover:underline"
                >
                  Limpar Filtros
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </Layout>
  );
}
