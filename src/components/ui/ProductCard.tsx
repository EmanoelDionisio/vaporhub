import React from 'react';
import { Link } from 'react-router-dom';
import { ShoppingCart, Star, Settings2 } from 'lucide-react';
import { Product } from '@/lib/data';
import { cn } from '@/lib/utils';

interface ProductCardProps {
  product: Product;
}

export const ProductCard: React.FC<ProductCardProps> = ({ product }) => {
  return (
    <div className="group relative bg-surface rounded-2xl border border-border overflow-hidden hover:shadow-xl hover:shadow-primary/10 transition-all duration-300">
      <div className="absolute top-3 left-3 z-10 flex flex-col gap-2">
        {product.brand === 'Vapor Hub' && (
          <span className="bg-primary text-background text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-wider shadow-sm text-center">
            Exclusivo
          </span>
        )}
        {product.isCustomizable && (
          <span className="bg-text-main text-white text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-wider shadow-sm flex items-center gap-1">
            <Settings2 className="size-3" /> Personalize
          </span>
        )}
      </div>

      {product.isSale && (
        <span className="absolute top-3 right-3 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded-full uppercase tracking-wider z-10">
          -15%
        </span>
      )}

      <Link to={`/produto/${product.id}`} className="block aspect-[4/5] overflow-hidden bg-gray-100 relative">
        <img
          src={product.image}
          alt={product.name}
          className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
        />
        <div className="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center backdrop-blur-[2px]">
          <span className="bg-white text-text-main font-bold py-2 px-6 rounded-full shadow-lg transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300 hover:bg-primary hover:text-white flex items-center gap-2">
            Ver Detalhes
          </span>
        </div>
      </Link>

      <div className="p-4">
        <div className="flex justify-between items-start mb-1">
          <Link to={`/produto/${product.id}`}>
            <h3 className="font-bold text-text-main text-lg leading-tight group-hover:text-primary transition-colors">
              {product.name}
            </h3>
          </Link>
        </div>

        <div className="flex items-center gap-1 mb-2">
          {[...Array(5)].map((_, i) => (
            <Star
              key={i}
              className={cn(
                "size-3.5",
                i < Math.floor(product.rating) ? "fill-yellow-400 text-yellow-400" : "text-gray-300"
              )}
            />
          ))}
          <span className="text-xs text-text-muted ml-1">({product.reviews})</span>
        </div>

        <div className="flex items-center justify-between mt-3">
          <div className="flex flex-col">
            <span className="text-xl font-bold text-text-main">
              {product.price.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
            </span>
            {product.originalPrice && (
              <span className="text-xs text-text-muted line-through">
                {product.originalPrice.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
              </span>
            )}
          </div>
          <button className="size-8 rounded-full bg-border flex items-center justify-center text-primary hover:bg-primary hover:text-white transition-colors">
            <ShoppingCart className="size-4" />
          </button>
        </div>
      </div>
    </div>
  );
}
