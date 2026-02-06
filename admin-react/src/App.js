import React, { useState, useEffect } from 'react';
import { BrowserRouter as Router, Route, Switch, Link } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from 'react-query';
import { ThemeProvider, createTheme, Box, Drawer, List, ListItem, ListItemIcon, ListItemText, CssBaseline } from '@mui/material';
import { Dashboard, Restaurant, LocalShipping, Settings, Kitchen } from '@mui/icons-material';

import Orders from './views/Orders';
import KDS from './views/KDS';
import Drivers from './views/Drivers';
import Settings from './views/Settings';

const theme = createTheme({
  palette: {
    primary: { main: '#d32f2f' },
    secondary: { main: '#388e3c' },
    background: { default: '#f5f5f5' }
  }
});

const queryClient = new QueryClient();

function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider theme={theme}>
        <CssBaseline />
        <Router>
          <Box sx={{ display: 'flex' }}>
            <Drawer variant="permanent" sx={{ width: 240, flexShrink: 0 }}>
              <List>
                <ListItem button component={Link} to="/">
                  <ListItemIcon><Dashboard /></ListItemIcon>
                  <ListItemText primary="Dashboard" />
                </ListItem>
                <ListItem button component={Link} to="/orders">
                  <ListItemIcon><Restaurant /></ListItemIcon>
                  <ListItemText primary="Orders" />
                </ListItem>
                <ListItem button component={Link} to="/kds">
                  <ListItemIcon><Kitchen /></ListItemIcon>
                  <ListItemText primary="Kitchen Display" />
                </ListItem>
                <ListItem button component={Link} to="/drivers">
                  <ListItemIcon><LocalShipping /></ListItemIcon>
                  <ListItemText primary="Drivers" />
                </ListItem>
                <ListItem button component={Link} to="/settings">
                  <ListItemIcon><Settings /></ListItemIcon>
                  <ListItemText primary="Settings" />
                </ListItem>
              </List>
            </Drawer>
            
            <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
              <Switch>
                <Route path="/orders" component={Orders} />
                <Route path="/kds" component={KDS} />
                <Route path="/drivers" component={Drivers} />
                <Route path="/settings" component={Settings} />
                <Route path="/" component={Dashboard} />
              </Switch>
            </Box>
          </Box>
        </Router>
      </ThemeProvider>
    </QueryClientProvider>
  );
}

export default App;
