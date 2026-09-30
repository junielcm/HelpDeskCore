<template>
  <div class="min-h-screen w-full flex items-center justify-center bg-slate-900 p-4">
    
    <!-- Contenedor Principal de la Vista -->
    <div class="relative w-full max-w-5xl h-[600px] bg-slate-900 rounded-3xl flex items-center justify-center overflow-hidden">
      
      <!-- Rectángulo de Fondo: Dividido en dos tonos de color por cada lado + Altura Reducida (h-[340px]) -->
      <div class="absolute w-[900px] h-[340px] rounded-2xl border border-white/10 flex justify-between items-center px-16 shadow-2xl overflow-hidden transition-all duration-700">
        
        <!-- Mitad Izquierda (Color Sólido/Gradiente Teal) -->
        <div class="absolute inset-y-0 left-0 w-1/2 bg-gradient-to-br from-teal-950 via-teal-900 to-slate-900 flex flex-col justify-center items-center text-center pr-12 pl-8">
          <div class="max-w-[220px] space-y-3">
            <h2 class="text-2xl font-extrabold tracking-tight text-white drop-shadow-md">¿Ya tienes una cuenta?</h2>
            <p class="text-slate-300 text-xs leading-relaxed">Inicia sesión para administrar tus solicitudes y tickets de soporte.</p>
            <button @click="isSignUp = false" class="px-6 py-2.5 rounded-xl bg-white text-teal-950 font-bold shadow-lg hover:bg-slate-100 transition-all text-sm">
              Iniciar Sesión
            </button>
          </div>
        </div>

        <!-- Mitad Derecha (Color Sólido/Gradiente Cyan-Indigo) -->
        <div class="absolute inset-y-0 right-0 w-1/2 bg-gradient-to-bl from-cyan-950 via-indigo-950 to-slate-900 flex flex-col justify-center items-center text-center pl-12 pr-8">
          <div class="max-w-[220px] space-y-3">
            <h2 class="text-2xl font-extrabold tracking-tight text-white drop-shadow-md">¿Aún no tienes cuenta?</h2>
            <p class="text-slate-300 text-xs leading-relaxed">Regístrate para comenzar a usar todas las herramientas del sistema.</p>
            <button @click="isSignUp = true" class="px-6 py-2.5 rounded-xl bg-white text-cyan-950 font-bold shadow-lg hover:bg-slate-100 transition-all text-sm">
              Registrarse
            </button>
          </div>
        </div>

      </div>

      <!-- Tarjeta Blanca Flotante Deslizante (Verticalmente alta y horizontalmente esbelta) -->
      <div class="absolute w-[360px] h-[540px] bg-white rounded-2xl shadow-[0_25px_60px_rgba(0,0,0,0.5)] z-10 flex flex-col justify-center px-8 py-8 transition-transform duration-700 ease-in-out"
           :style="{ transform: isSignUp ? 'translateX(235px)' : 'translateX(-235px)' }">
        
        <!-- Formulario de Iniciar Sesión -->
        <div v-if="!isSignUp" class="w-full space-y-6">
          <div class="border-l-4 border-teal-600 pl-3">
            <h2 class="text-2xl font-bold text-slate-900">Iniciar Sesión</h2>
          </div>

          <div v-if="authStore.error" class="whitespace-pre-line rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
            {{ authStore.error }}
          </div>
          
          <form @submit.prevent="handleLogin" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Correo</label>
              <input v-model="loginForm.email" type="email" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-500 focus:outline-none text-slate-800 text-sm bg-slate-50/50 transition-all" placeholder="correo@ejemplo.com" />
            </div>
            <div>
              <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Contraseña</label>
              <input v-model="loginForm.password" type="password" required class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-teal-500 focus:outline-none text-slate-800 text-sm bg-slate-50/50 transition-all" placeholder="••••••••" />
            </div>

            <button type="submit" :disabled="loading" class="w-full py-3.5 bg-gradient-to-r from-teal-600 to-cyan-600 text-white font-semibold rounded-xl shadow-lg shadow-teal-900/20 hover:opacity-90 transition-all text-sm mt-3">
              {{ loading ? 'Ingresando...' : 'Entrar al Sistema' }}
            </button>
          </form>

          <div class="text-center pt-3">
            <a href="#" @click.prevent class="text-xs text-slate-400 hover:text-teal-600 transition-colors font-medium">¿Olvidaste tu contraseña?</a>
          </div>
        </div>

        <!-- Formulario de Registro -->
        <div v-else class="w-full space-y-4">
          <div class="border-l-4 border-cyan-600 pl-3">
            <h2 class="text-2xl font-bold text-slate-900">Crear Cuenta</h2>
          </div>

          <div v-if="authStore.error" class="whitespace-pre-line rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-medium text-red-700">
            {{ authStore.error }}
          </div>
          
          <form @submit.prevent="handleRegister" class="space-y-3">
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Usuario</label>
              <input v-model="registerForm.name" type="text" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-cyan-500 focus:outline-none text-slate-800 text-sm bg-slate-50/50 transition-all" placeholder="Nombre completo" />
            </div>
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Correo</label>
              <input v-model="registerForm.email" type="email" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-cyan-500 focus:outline-none text-slate-800 text-sm bg-slate-50/50 transition-all" placeholder="correo@ejemplo.com" />
            </div>
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Contraseña</label>
              <input v-model="registerForm.password" type="password" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-cyan-500 focus:outline-none text-slate-800 text-sm bg-slate-50/50 transition-all" placeholder="••••••••" />
            </div>
            <div>
              <label class="block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Confirmar</label>
              <input v-model="registerForm.password_confirmation" type="password" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:ring-2 focus:ring-cyan-500 focus:outline-none text-slate-800 text-sm bg-slate-50/50 transition-all" placeholder="••••••••" />
            </div>

            <button type="submit" :disabled="loading" class="w-full py-3 bg-gradient-to-r from-cyan-600 to-teal-600 text-white font-semibold rounded-xl shadow-lg shadow-cyan-900/20 hover:opacity-90 transition-all text-sm mt-2">
              {{ loading ? 'Registrando...' : 'Completar Registro' }}
            </button>
          </form>
        </div>

      </div>

    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const authStore = useAuthStore()
const router = useRouter()

const isSignUp = ref(false)
const loading = ref(false)

const loginForm = ref({ email: '', password: '' })
const registerForm = ref({ name: '', email: '', password: '', password_confirmation: '' })

const handleLogin = async () => {
  loading.value = true
  try {
    await authStore.login(loginForm.value)
    router.push('/')
  } catch (error) {
    // El detalle del error queda expuesto en authStore.error.
  } finally {
    loading.value = false
  }
}

const handleRegister = async () => {
  loading.value = true
  try {
    await authStore.register(registerForm.value)
    router.push('/')
  } catch (error) {
    // El detalle del error queda expuesto en authStore.error.
  } finally {
    loading.value = false
  }
}
</script>