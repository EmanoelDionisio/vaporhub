import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { Layout } from '@/components/layout/Layout';
import { products } from '@/lib/data';
import { CreditCard, Truck, ShieldCheck, MapPin, CheckCircle, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Checkout() {
    const [step, setStep] = useState(1);
    const [success, setSuccess] = useState(false);

    // Mocked cart info matching Cart.tsx
    const cartItems = [
        { ...products[0], quantity: 2, selectedColor: 'Laranja Vibrante' },
        { ...products[1], quantity: 1, selectedColor: 'Verde' },
    ];

    const subtotal = cartItems.reduce((acc, item) => acc + item.price * item.quantity, 0);
    const shipping = 15.90;
    const total = subtotal + shipping;

    const handleComplete = (e: React.FormEvent) => {
        e.preventDefault();
        setSuccess(true);
    };

    if (success) {
        return (
            <Layout>
                <div className="max-w-[800px] mx-auto px-4 py-20 text-center">
                    <div className="flex justify-center mb-6">
                        <CheckCircle className="size-24 text-green-500" />
                    </div>
                    <h1 className="text-4xl font-extrabold text-text-main mb-4">Pedido Confirmado!</h1>
                    <p className="text-xl text-text-muted mb-8">
                        Obrigado por comprar conosco. Seu pedido #PA-8204 foi recebido e está sendo processado.
                    </p>
                    <div className="bg-surface border border-border rounded-2xl p-6 md:p-10 mb-8 inline-block text-left w-full max-w-lg shadow-sm">
                        <h3 className="font-bold text-lg mb-4 text-text-main">Detalhes da Entrega</h3>
                        <p className="text-text-muted">
                            <strong className="text-text-main">Emanoel Dionisio</strong><br />
                            Av. Paulista, 1000 - Apto 45<br />
                            São Paulo, SP - 01310-100<br />
                            Previsão: 3 a 5 dias úteis
                        </p>
                    </div>
                    <div className="mt-8">
                        <Link
                            to="/loja"
                            className="inline-flex bg-primary hover:bg-primary-hover text-background font-bold py-4 px-10 rounded-xl shadow-lg transition-colors"
                        >
                            Voltar para a Loja
                        </Link>
                    </div>
                </div>
            </Layout>
        );
    }

    return (
        <Layout>
            <div className="max-w-[1280px] mx-auto px-4 lg:px-10 py-8 md:py-12">
                <div className="flex items-center gap-2 text-sm text-text-muted font-medium mb-8">
                    <Link to="/carrinho" className="hover:text-primary transition-colors">Carrinho</Link>
                    <ChevronRight className="size-4" />
                    <span className={cn(step >= 1 ? "text-primary font-bold" : "")}>Identificação & Entrega</span>
                    <ChevronRight className="size-4" />
                    <span className={cn(step >= 2 ? "text-primary font-bold" : "")}>Pagamento</span>
                </div>

                <h1 className="text-3xl font-extrabold text-text-main mb-8">Finalizar Pedido</h1>

                <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">
                    {/* Main Form Area */}
                    <div className="lg:col-span-8">
                        <form onSubmit={step === 2 ? handleComplete : (e) => { e.preventDefault(); setStep(2); }}>

                            {/* Entregas Section */}
                            <div className={cn(
                                "bg-surface rounded-2xl border border-border p-6 md:p-8 shadow-sm transition-all duration-300",
                                step === 1 ? "ring-2 ring-primary ring-opacity-50" : "opacity-70"
                            )}>
                                <div className="flex items-center gap-3 mb-6">
                                    <div className={cn("size-10 rounded-full flex items-center justify-center font-bold text-white", step >= 1 ? "bg-primary" : "bg-gray-300")}>1</div>
                                    <h2 className="text-2xl font-bold flex items-center gap-2"><MapPin className="size-6 text-primary" /> Informações de Entrega</h2>
                                </div>

                                {step === 1 ? (
                                    <div className="space-y-4 animate-in fade-in slide-in-from-top-4">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <label className="text-sm font-bold text-text-main">Nome Completo</label>
                                                <input required type="text" placeholder="Seu nome" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                            </div>
                                            <div className="space-y-2">
                                                <label className="text-sm font-bold text-text-main">Email</label>
                                                <input required type="email" placeholder="seu@email.com" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                            </div>
                                            <div className="space-y-2">
                                                <label className="text-sm font-bold text-text-main">CPF</label>
                                                <input required type="text" placeholder="000.000.000-00" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                            </div>
                                            <div className="space-y-2">
                                                <label className="text-sm font-bold text-text-main">Telefone</label>
                                                <input required type="text" placeholder="(11) 99999-9999" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                            </div>
                                        </div>

                                        <div className="pt-4 mt-4 border-t border-border space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <div className="space-y-2 md:col-span-1">
                                                    <label className="text-sm font-bold text-text-main">CEP</label>
                                                    <input required type="text" placeholder="00000-000" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                                </div>
                                                <div className="space-y-2 md:col-span-2">
                                                    <label className="text-sm font-bold text-text-main">Endereço</label>
                                                    <input required type="text" placeholder="Rua..." className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                                <div className="space-y-2 col-span-1">
                                                    <label className="text-sm font-bold text-text-main">Número</label>
                                                    <input required type="text" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                                </div>
                                                <div className="space-y-2 col-span-1 md:col-span-1">
                                                    <label className="text-sm font-bold text-text-main">Compl.</label>
                                                    <input type="text" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                                </div>
                                                <div className="space-y-2 col-span-2 md:col-span-2">
                                                    <label className="text-sm font-bold text-text-main">Bairro</label>
                                                    <input required type="text" className="w-full bg-background border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 text-text-main" />
                                                </div>
                                            </div>
                                        </div>

                                        <div className="pt-6">
                                            <button type="submit" className="w-full md:w-auto bg-primary hover:bg-primary-hover text-background font-bold py-4 px-10 rounded-xl shadow-lg transition-colors ml-auto flex items-center justify-center gap-2">
                                                Ir para Pagamento <ChevronRight className="size-5" />
                                            </button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex justify-between items-center text-sm">
                                        <div className="text-text-muted">
                                            Emanoel Dionisio • São Paulo, SP
                                        </div>
                                        <button type="button" onClick={() => setStep(1)} className="text-primary font-bold hover:underline">
                                            Editar
                                        </button>
                                    </div>
                                )}
                            </div>

                            {/* Pagamento Section */}
                            <div className={cn(
                                "bg-surface rounded-2xl border border-border p-6 md:p-8 shadow-sm transition-all duration-300 mt-6",
                                step === 2 ? "ring-2 ring-primary ring-opacity-50" : "opacity-60 grayscale-[50%]"
                            )}>
                                <div className="flex items-center gap-3 mb-6">
                                    <div className={cn("size-10 rounded-full flex items-center justify-center font-bold text-white", step === 2 ? "bg-primary" : "bg-gray-300")}>2</div>
                                    <h2 className="text-2xl font-bold flex items-center gap-2"><CreditCard className="size-6 text-primary" /> Pagamento</h2>
                                </div>

                                {step === 2 && (
                                    <div className="space-y-6 animate-in fade-in slide-in-from-top-4">
                                        {/* Payment methods toggle */}
                                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                                            <label className="cursor-pointer">
                                                <input type="radio" name="payment" className="peer sr-only" defaultChecked />
                                                <div className="border-2 border-border rounded-xl p-4 text-center hover:bg-gray-50 peer-checked:border-primary peer-checked:bg-primary/5 transition-all">
                                                    <CreditCard className="size-6 mx-auto mb-2 text-text-main peer-checked:text-primary" />
                                                    <span className="font-bold text-sm block">Cartão de Crédito</span>
                                                </div>
                                            </label>
                                            <label className="cursor-pointer">
                                                <input type="radio" name="payment" className="peer sr-only" />
                                                <div className="border-2 border-border rounded-xl p-4 text-center hover:bg-gray-50 peer-checked:border-primary peer-checked:bg-primary/5 transition-all">
                                                    <div className="size-6 mx-auto mb-2 bg-text-main rounded-sm flex items-center justify-center text-[10px] text-white font-bold peer-checked:bg-primary transition-colors">PIX</div>
                                                    <span className="font-bold text-sm block">Pix - 5% OFF</span>
                                                </div>
                                            </label>
                                            <label className="cursor-pointer col-span-2 md:col-span-1">
                                                <input type="radio" name="payment" className="peer sr-only" />
                                                <div className="border-2 border-border rounded-xl p-4 text-center hover:bg-gray-50 peer-checked:border-primary peer-checked:bg-primary/5 transition-all">
                                                    <div className="size-6 mx-auto mb-2 flex space-x-1 items-center justify-center">
                                                        <span className="block w-1.5 h-4 bg-gray-400"></span><span className="block w-1.5 h-4 bg-gray-400"></span><span className="block w-1 h-4 bg-gray-400"></span>
                                                    </div>
                                                    <span className="font-bold text-sm block">Boleto</span>
                                                </div>
                                            </label>
                                        </div>

                                        {/* Credit Card Form Mock */}
                                        <div className="bg-background rounded-xl p-5 border border-border space-y-4">
                                            <div className="space-y-2">
                                                <label className="text-sm font-bold text-text-main">Número do Cartão</label>
                                                <input required type="text" placeholder="0000 0000 0000 0000" className="w-full bg-white border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 font-mono" />
                                            </div>
                                            <div className="space-y-2">
                                                <label className="text-sm font-bold text-text-main">Nome no Cartão</label>
                                                <input required type="text" placeholder="Como impresso no cartão" className="w-full bg-white border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 uppercase" />
                                            </div>
                                            <div className="grid grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <label className="text-sm font-bold text-text-main">Validade (MM/AA)</label>
                                                    <input required type="text" placeholder="MM/AA" className="w-full bg-white border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 font-mono" />
                                                </div>
                                                <div className="space-y-2">
                                                    <label className="text-sm font-bold text-text-main">CVV</label>
                                                    <input required type="text" placeholder="123" className="w-full bg-white border border-border rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary/50 font-mono" />
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" className="w-full bg-primary hover:bg-primary-hover text-background font-extrabold text-lg py-5 px-6 rounded-xl shadow-lg shadow-primary/20 transition-all active:scale-[0.99] flex items-center justify-center gap-2 mt-4">
                                            Finalizar Pedido de {total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                        </button>

                                        <p className="text-center text-xs text-text-muted mt-4 flex justify-center items-center gap-1">
                                            <ShieldCheck className="size-4 text-green-500" /> Transação protegida por criptografia de ponta a ponta
                                        </p>
                                    </div>
                                )}
                            </div>
                        </form>
                    </div>

                    {/* Sidebar Area: Order Summary */}
                    <div className="lg:col-span-4">
                        <div className="bg-surface rounded-2xl border border-border p-6 shadow-sm sticky top-24">
                            <h3 className="text-lg font-bold text-text-main mb-6 pb-4 border-b border-border">Resumo do Pedido</h3>

                            {/* Items List Mini */}
                            <div className="space-y-4 mb-6 pb-4 border-b border-border max-h-[300px] overflow-y-auto no-scrollbar">
                                {cartItems.map(item => (
                                    <div key={item.id} className="flex gap-3">
                                        <div className="relative">
                                            <img src={item.image} alt={item.name} className="w-16 h-16 object-cover rounded-lg border border-border bg-background" />
                                            <span className="absolute -top-2 -right-2 bg-text-main text-white text-[10px] font-bold size-5 rounded-full flex items-center justify-center">{item.quantity}</span>
                                        </div>
                                        <div className="flex-1 flex flex-col justify-center">
                                            <span className="text-sm font-bold text-text-main line-clamp-2 leading-tight">{item.name}</span>
                                            <div className="mt-1 flex justify-between items-center w-full">
                                                {item.selectedColor && <span className="text-xs text-text-muted">{item.selectedColor}</span>}
                                                <span className="text-sm font-bold ml-auto">
                                                    {(item.price * item.quantity).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {/* Totals */}
                            <div className="space-y-3 mb-6">
                                <div className="flex justify-between text-text-muted text-sm">
                                    <span>Subtotal</span>
                                    <span className="font-medium text-text-main">{subtotal.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
                                </div>
                                <div className="flex justify-between text-text-muted text-sm">
                                    <span>Frete <span className="text-xs flex items-center gap-1 bg-gray-100 rounded px-1 w-fit"><Truck className="size-3" /> Jadlog</span></span>
                                    <span className="font-medium text-text-main">{shipping.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}</span>
                                </div>

                                <div className="pt-4 border-t border-border flex justify-between items-end">
                                    <span className="text-lg font-bold text-text-main">Total</span>
                                    <div className="flex flex-col items-end">
                                        <span className="text-2xl font-extrabold text-primary">
                                            {total.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                                        </span>
                                        <span className="text-xs text-text-muted">em até 3x sem juros</span>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </Layout>
    );
}
